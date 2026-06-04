<?php
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';

// Require Admin permission (or any other appropriate permission logic)
requireRole('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_media_type') {
        $name = clean($_POST['name']);
        try {
            $stmt = $pdo->prepare("INSERT INTO media_types (name) VALUES (?)");
            $stmt->execute([$name]);
            header("Location: media_types.php?msg=added");
            exit;
        } catch (PDOException $e) {
            header("Location: media_types.php?error=" . urlencode("Could not add media type. It might already exist."));
            exit;
        }
    } elseif ($_POST['action'] === 'edit_media_type') {
        $id = intval($_POST['id']);
        $name = clean($_POST['name']);
        try {
            $stmt = $pdo->prepare("UPDATE media_types SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            header("Location: media_types.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            header("Location: media_types.php?error=" . urlencode("Could not update media type. It might already exist."));
            exit;
        }
    } elseif ($_POST['action'] === 'delete_media_type') {
        $id = intval($_POST['id']);
        try {
            include_once __DIR__ . '/../../includes/trash_helper.php';
            $trashId = move_row_to_trash($pdo, 'media_types', 'id', $id, $_SESSION['user_id'] ?? null, 'Media type deleted via admin UI');
            if ($trashId) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to move media type to trash.']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$activePage = 'media_types';
$pageTitle = 'Media Types Master';
include_once __DIR__ . '/../../includes/header.php';

// Fetch media types
$stmt = $pdo->query("SELECT * FROM media_types ORDER BY name ASC");
$mediaTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Media Types</h2>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> Add Media Type
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div style="padding: 1rem; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 1rem;">
            Successfully updated!
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div style="padding: 1rem; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 8px; margin-bottom: 1rem;">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Media Type Name</th>
                    <th style="text-align: right; width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mediaTypes as $mt): ?>
                    <tr>
                        <td><?php echo $mt['id']; ?></td>
                        <td><div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($mt['name']); ?></div></td>
                        <td style="text-align: right;">
                            <button class="btn-icon btn-edit" onclick="editMediaType(<?php echo htmlspecialchars(json_encode($mt)); ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-delete" onclick="deleteMediaType(<?php echo $mt['id']; ?>)" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($mediaTypes)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: #94a3b8; padding: 2rem;">No media types found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="mediaTypeModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background: white; margin: 10% auto; padding: 2rem; border-radius: 12px; max-width: 500px; position: relative;">
        <span class="close" onclick="closeModal()" style="cursor: pointer; position: absolute; right: 1.5rem; top: 1.5rem; font-size: 1.5rem;">&times;</span>
        <h2 id="modalTitle" style="margin-top: 0;">Add Media Type</h2>
        <form method="POST" id="mediaTypeForm">
            <input type="hidden" name="action" id="formAction" value="add_media_type">
            <input type="hidden" name="id" id="typeId">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Media Type Name</label>
                <input type="text" name="name" id="f_name" required style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
            </div>
            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="button" class="btn" onclick="closeModal()" style="background: #f1f5f9; color: #475569; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; cursor: pointer; margin-right: 0.5rem;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: var(--primary); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; cursor: pointer;">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = 'Add Media Type';
    document.getElementById('formAction').value = 'add_media_type';
    document.getElementById('mediaTypeForm').reset();
    document.getElementById('typeId').value = '';
    document.getElementById('mediaTypeModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('mediaTypeModal').style.display = 'none';
}

function editMediaType(type) {
    document.getElementById('modalTitle').innerText = 'Edit Media Type';
    document.getElementById('formAction').value = 'edit_media_type';
    document.getElementById('typeId').value = type.id;
    document.getElementById('f_name').value = type.name;
    document.getElementById('mediaTypeModal').style.display = 'block';
}

function deleteMediaType(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('media_types.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_media_type&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error!', 'An unexpected error occurred', 'error');
            });
        }
    });
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
