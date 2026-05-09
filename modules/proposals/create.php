<?php
$activePage = 'proposals';
$pageTitle = 'Create New Proposal';
$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

if (!hasRole(['admin', 'sales'])) {
    echo "<div class='card'>Access Denied. You do not have permission to create proposals.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch Data
$clients = $pdo->query("SELECT id, name, city, contact_person FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
$sitesQuery = "
    SELECT 
        s.*, 
        (SELECT filename FROM site_images WHERE site_id = s.id LIMIT 1) as thumbnail 
    FROM sites s 
    ORDER BY s.site_code ASC";
$sites = $pdo->query($sitesQuery)->fetchAll();

// Fetch filter values
$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$genres = $pdo->query("SELECT DISTINCT genre FROM sites WHERE genre IS NOT NULL AND genre != '' ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="proposal-full-wrapper">
    <!-- WIZARD HEADER -->
    <div class="wizard-header" style="max-width: 600px; margin: 0 auto 1.5rem auto; display: flex; align-items: center; justify-content: space-between; position: relative;">
        <!-- Connecting Line -->
        <div style="position: absolute; top: 22.5px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1; transform: translateY(-50%); border-radius: 4px;"></div>
        <div id="wizard-progress-line" style="position: absolute; top: 22.5px; left: 10%; width: 0%; height: 4px; background: var(--primary); z-index: 1; transform: translateY(-50%); transition: width 0.4s ease; border-radius: 4px;"></div>
        
        <!-- Step 1 -->
        <div id="step-tab-1" class="wizard-step active" style="position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; background: #f8fafc; padding: 0 1rem;">
            <div class="step-circle" style="width: 45px; height: 45px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; border: 4px solid #f8fafc; box-shadow: 0 0 0 3px var(--primary); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
                1
            </div>
            <span class="step-label" style="font-weight: 800; color: var(--primary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.3s;">Client & Details</span>
        </div>

        <!-- Step 2 -->
        <div id="step-tab-2" class="wizard-step" style="position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; background: #f8fafc; padding: 0 1rem;">
            <div class="step-circle" style="width: 45px; height: 45px; border-radius: 50%; background: white; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; border: 4px solid #f8fafc; box-shadow: 0 0 0 3px #e2e8f0; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
                2
            </div>
            <span class="step-label" style="font-weight: 700; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.3s;">Assets & Pricing</span>
        </div>
    </div>

    <!-- STEP 1 -->
    <div id="step-1">
        <div class="p-panel" style="max-width: 1100px; margin: 0 auto;">
            <div class="p-header"> Basic Details & Duration</div>
            
            <!-- Row 1: Campaign, Client, Contact -->
            <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom: 1rem;">
                <div class="form-group">
                    <label>Campaign Name <span style="color:red;">*</span></label>
                    <input type="text" id="campaign_name" class="p-input" placeholder="Enter campaign name (e.g. Summer Sale 2024)" style="height: 38px;">
                </div>
                <div class="form-group">
                    <label style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Company / Client <span style="color:red;">*</span></span>
                        <button type="button" class="btn-text" onclick="openClientModal()" style="font-size: 0.7rem; color: var(--primary); background: none; border: none; cursor: pointer; padding: 0;">
                            <i class="fas fa-plus-circle"></i> New
                        </button>
                    </label>
                    <select id="client_id" class="p-input" style="height: 38px;" onchange="handleClientChange()">
                        <option value="">-- Choose Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-contact="<?php echo htmlspecialchars($c['contact_person'] ?? ''); ?>">
                                <?php echo $c['name']; ?> <?php echo $c['city'] ? "({$c['city']})" : ""; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" id="contact_person" class="p-input" placeholder="Contact person name" style="height: 38px;">
                </div>
            </div>

            <!-- Row 2: Dates & Total Days -->
            <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr 0.6fr 1.4fr;">
                <div class="form-group">
                    <label>From Date <span style="color:red;">*</span></label>
                    <input type="date" id="start_date" class="p-input" style="height: 38px;" onchange="calculateEndDate()">
                </div>
                <div class="form-group">
                    <label>To Date <span style="color:red;">*</span></label>
                    <input type="date" id="end_date" class="p-input" style="height: 38px;" onchange="calculateTotalDays()">
                </div>
                <div class="form-group">
                    <label>Delivery Date</label>
                    <input type="date" id="delivery_date" class="p-input" style="height: 38px;">
                </div>
                <div class="form-group">
                    <label>Total Days</label>
                    <input type="number" id="total_days" class="p-input" placeholder="Days" style="height: 38px;" oninput="calculateEndDate()">
                </div>
                <div class="form-group">
                    <label>Remark (Optional)</label>
                    <input type="text" id="remark" class="p-input" placeholder="Any additional notes..." style="height: 38px;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button class="btn btn-primary" onclick="goToStep2()" style="width: 250px; height: 44px; border-radius: 10px; font-weight: 800; font-size: 0.9rem;">
                    Next Step: Select Assets <i class="fas fa-arrow-right" style="margin-left: 0.5rem;"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- STEP 2 -->
    <div id="step-2" style="display: none;">
        <div style="margin-bottom: 1rem;">
            <button class="btn btn-secondary" onclick="goToStep1()" style="background: white; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; cursor: pointer; color: #475569;">
                <i class="fas fa-arrow-left"></i> Back to Details
            </button>
        </div>

        <!-- Top: Full Width Asset Selection -->
        <!-- Top: Media Search Panel -->
        <div class="p-panel" style="margin-bottom: 1.5rem; border-left: 4px solid var(--primary);">
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-search"></i> Media Search
            </div>
            
            <div class="media-search-grid">
                <!-- Ownership & Availability -->
                <div class="search-row" style="margin-bottom: 1rem;">
                    <div class="search-group">
                        <div class="radio-group">
                            <label><input type="radio" name="ownership" value="all" checked onchange="filterSites()"> Self / Vendor / Third Party</label>
                            <label><input type="radio" name="ownership" value="HA" onchange="filterSites()"> Self</label>
                            <label><input type="radio" name="ownership" value="TA" onchange="filterSites()"> Vendor</label>
                        </div>
                    </div>
                    <div class="search-group" style="margin-left: 2rem;">
                        <div class="radio-group">
                            <label><input type="radio" name="availability" value="available" checked onchange="filterSites()"> Available</label>
                            <label><input type="radio" name="availability" value="all" onchange="filterSites()"> All Media</label>
                        </div>
                    </div>
                </div>

                <!-- Main Filters -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <select id="filter-genre" class="p-input" onchange="filterSites()">
                        <option value="">Select Genre</option>
                        <?php foreach($genres as $g): ?> <option value="<?php echo $g; ?>"><?php echo $g; ?></option> <?php endforeach; ?>
                    </select>
                    <select id="filter-type" class="p-input" onchange="filterSites()">
                        <option value="">Select Media Type</option>
                        <?php foreach($mediaTypes as $t): ?> <option value="<?php echo $t; ?>"><?php echo $t; ?></option> <?php endforeach; ?>
                    </select>
                    <select id="filter-state" class="p-input" onchange="filterSites()">
                        <option value="">Select State</option>
                        <?php foreach($states as $s): ?> <option value="<?php echo $s; ?>"><?php echo $s; ?></option> <?php endforeach; ?>
                    </select>
                    <select id="filter-city" class="p-input" onchange="filterSites()">
                        <option value="">Select City</option>
                        <?php foreach($cities as $c): ?> <option value="<?php echo $c; ?>"><?php echo $c; ?></option> <?php endforeach; ?>
                    </select>
                    <select id="filter-illumination" class="p-input" onchange="filterSites()">
                        <option value="">Select Illumination</option>
                        <?php foreach($illuminations as $i): ?> <option value="<?php echo $i; ?>"><?php echo $i; ?></option> <?php endforeach; ?>
                    </select>
                    <div class="search-input-wrap" style="grid-column: span 2;">
                        <input type="text" id="site-search" class="p-input" placeholder="Search site name, code or location..." onkeyup="filterSites()">
                    </div>
                    <button class="btn btn-primary" onclick="filterSites()" style="height: 38px;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </div>

        <div class="p-panel" id="asset-plan-panel" style="margin-bottom: 2rem;">
            <div class="p-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span>Asset Selection & Plan Pricing</span>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <div class="selection-stats">Selected: <span id="selected-count">0</span> sites</div>
                </div>
            </div>

            <div class="site-list-container" style="max-height: 600px; overflow-y: auto;">
                <table class="crs-table selection-table" id="asset-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 40px;"><i class="far fa-check-square"></i></th>
                                <th>ASSET DETAILS</th>
                                <th>PREVIEW</th>
                                <th>DIMENSIONS</th>
                                <th>PRICING (MONTHLY)</th>
                                <th>SALE RATE (₹)</th>
                                <th style="width: 140px; text-align: right;">TOTAL</th>
                            </tr>
                        </thead>
                    <tbody id="asset-body">
                        <?php $sno = 1; foreach ($sites as $s): 
                            $sqft = $s['width'] * $s['height'];
                            $dailyRate = $s['card_rate'] / 30;
                        ?>
                        <tr class="site-row" 
                            id="row-<?php echo $s['id']; ?>"
                            data-id="<?php echo $s['id']; ?>" 
                            data-name="<?php echo $s['name']; ?>" 
                            data-code="<?php echo $s['site_code']; ?>"
                            data-location="<?php echo $s['location']; ?>"
                            data-city="<?php echo $s['city']; ?>"
                            data-state="<?php echo $s['state']; ?>"
                            data-type="<?php echo $s['type']; ?>"
                            data-genre="<?php echo $s['genre']; ?>"
                            data-illumination="<?php echo $s['light_type']; ?>"
                            data-status="<?php echo $s['status']; ?>"
                            data-rate="<?php echo $s['card_rate']; ?>" 
                            data-prate="<?php echo $s['purchase_rate']; ?>" 
                            data-owner="<?php echo $s['owner_type']; ?>"
                            data-sqft="<?php echo $sqft; ?>">
                            <td class="sno-cell"><?php echo $sno++; ?></td>
                            <td><input type="checkbox" class="asset-chk" onclick="toggleSite('<?php echo $s['id']; ?>')"></td>
                            <td>
                                <div style="font-weight: 700; color: #334155; margin-bottom: 2px; white-space: normal; line-height: 1.4;"><?php echo $s['location']; ?></div>
                                <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 600;">
                                    <span style="color: var(--primary);"><?php echo $s['city']; ?></span> • <?php echo $s['type']; ?> • <?php echo $s['light_type']; ?> • <span class="badge-<?php echo strtolower($s['owner_type']); ?>"><?php echo $s['owner_type']; ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if ($s['thumbnail']): ?>
                                    <img src="../../uploads/sites/<?php echo $s['thumbnail']; ?>" class="site-thumb" onclick="window.open(this.src)">
                                <?php else: ?>
                                    <span class="no-img">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #475569;"><?php echo $s['width']; ?>' x <?php echo $s['height']; ?>'</div>
                                <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo number_format($sqft); ?> SQFT</div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b;">₹<?php echo number_format($s['card_rate']); ?></div>
                                <div style="font-size: 0.7rem; color: #94a3b8;">₹<?php echo number_format($dailyRate, 2); ?> / day</div>
                            </td>
                            <td>
                                <input type="number" class="p-input sale-rate-input" 
                                       value="<?php echo $s['card_rate']; ?>" 
                                       oninput="updateSitePrice('<?php echo $s['id']; ?>', this.value)"
                                       disabled style="width: 110px; height: 32px; font-size: 0.85rem;">
                            </td>
                            <td class="total-cell" style="font-weight: 800; color: var(--primary); text-align: right; font-size: 1rem;">₹0</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="pagination-wrap">
                <div class="pg-info">
                    Showing <span id="pg-start">1</span> to <span id="pg-end">10</span> of <span id="pg-total"><?php echo count($sites); ?></span> sites
                </div>
                <div class="pg-controls" id="pg-numbers">
                    <!-- JS will populate -->
                </div>
                <div class="pg-size">
                    <select id="pg-limit" onchange="changePageSize()" class="p-input" style="width: 100px; height: 38px; font-size: 0.8rem;">
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100">100 / page</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Bottom: Configuration Grid -->
        <div class="proposal-bottom-grid" style="grid-template-columns: 1fr 1fr;">
            <!-- Pricing Controls -->
            <div class="p-panel">
                <div class="p-header"> Pricing & Costs</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Discount (%)</label>
                        <input type="number" id="global_discount" value="0" class="p-input" oninput="recalcAll()" style="height: 34px;">
                    </div>
                    <div class="form-group">
                        <label>Markup (%)</label>
                        <input type="number" id="global_markup" value="0" class="p-input" oninput="recalcAll()" style="height: 34px;">
                    </div>
                </div>
                <div class="form-grid" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Printing (₹)</label>
                        <input type="number" id="print_cost" value="0" class="p-input" oninput="recalcAll()" style="height: 34px;">
                    </div>
                    <div class="form-group">
                        <label>Mounting (₹)</label>
                        <input type="number" id="mount_cost" value="0" class="p-input" oninput="recalcAll()" style="height: 34px;">
                    </div>
                </div>
            </div>

            <!-- Final Summary -->
            <div class="p-panel summary-box" style="background: #f8fafc; display: flex; flex-direction: column;">
                <div class="p-header"> Summary</div>
                <div style="flex: 1;">
                    <div class="stat-row">
                        <span>Sites Selected:</span>
                        <span id="selected-count-btm" style="font-weight: 800;">0</span>
                    </div>
                    <div class="stat-row">
                        <span>Display Cost:</span>
                        <span id="sum-display-btm">₹0</span>
                    </div>
                    
                    <div style="border-top: 1px dashed #e2e8f0; padding-top: 1rem; margin-top: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: var(--secondary);">TAX TYPE</span>
                            <select id="tax-type" class="p-input" onchange="recalcAll()" style="width: 140px; height: 28px; font-size: 0.7rem; padding: 0 0.4rem; border-radius: 6px;">
                                <option value="igst">IGST (18%)</option>
                                <option value="cgst_sgst">CGST/SGST (9%+9%)</option>
                            </select>
                        </div>
                        <div id="tax-breakdown">
                            <div class="stat-row">
                                <span>GST (18%):</span>
                                <span id="sum-tax-btm">₹0</span>
                            </div>
                        </div>
                    </div>

                    <div class="grand-total" style="border-top: 2px solid #e2e8f0; padding-top: 0.75rem; margin-top: 0.5rem; color: var(--primary); font-weight: 900;">
                        <div style="font-size: 0.65rem; color: var(--secondary); margin-bottom: 0.1rem;">GRAND TOTAL</div>
                        <div id="sum-grand-btm" style="font-size: 1.5rem;">₹0</div>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="saveProposal()" style="width: 100%; margin-top: 0.75rem; height: 40px; border-radius: 8px; font-weight: 800; font-size: 0.85rem;">
                    GENERATE PROPOSAL
                </button>
            </div>
        </div>
    </div>
</div>

<div id="clientModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>New Client</h3>
            <button class="close-modal" onclick="closeClientModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-grid" style="margin-bottom: 1rem;">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="new_client_city" class="p-input">
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" id="new_client_name" class="p-input">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Contact Person</label>
                <input type="text" id="new_client_contact" class="p-input">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="new_client_phone" class="p-input">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="new_client_email" class="p-input">
                </div>
            </div>
            <div class="form-group" style="margin-top: 1rem;">
                <label>Address / Location</label>
                <input type="text" id="new_client_address" class="p-input">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeClientModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitQuickClient()">Save Client</button>
        </div>
    </div>
</div>

<style>
/* Search Panel Styles */
.media-search-grid { padding: 0.5rem 0; }
.search-row { display: flex; align-items: center; }
.radio-group { display: flex; gap: 1.5rem; }
.radio-group label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; }
.radio-group input[type="radio"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }

/* Layout Structure */
.proposal-full-wrapper { max-width: 100%; margin: 0 auto; padding: 1.5rem; background: #f8fafc; min-height: 100vh; }

.proposal-bottom-grid { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 1.5rem; 
    margin-top: 2rem; 
}

/* Step Indicators */
.step-indicator { 
    background: var(--primary); 
    color: white; 
    padding: 0.35rem 1rem; 
    border-radius: 50px; 
    font-size: 0.75rem; 
    font-weight: 800; 
    text-transform: uppercase; 
    letter-spacing: 0.05em;
}

/* Panel Styling */
.p-panel { 
    background: white; 
    border-radius: 12px; 
    padding: 1rem; 
    border: 1px solid #e2e8f0; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
}

.p-header { 
    font-weight: 800; 
    font-size: 0.95rem; 
    color: var(--primary); 
    margin-bottom: 1rem; 
    border-bottom: 2px solid #f1f5f9; 
    padding-bottom: 0.5rem; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
}

/* Form Elements */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.form-group { margin-bottom: 0.75rem; }
.form-group label { display: block; font-size: 0.75rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.025em; }

.p-input { 
    width: 100%; padding: 0.5rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px; 
    font-family: inherit; font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease; 
    background: #fcfcfc;
}
.p-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); outline: none; background: white; }

/* Table Styling */
.site-list-container { overflow-x: auto; border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; background: white; }
.crs-table { width: 100%; border-collapse: collapse; }
.crs-table th { background: transparent; padding: 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; font-weight: 800; border-bottom: 1px solid #f1f5f9; text-align: left; }
.crs-table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f8fafc; color: #64748b; font-size: 0.85rem; }

.site-row { transition: all 0.2s; }
.site-row:hover { background: #f8fafc; }
.site-row.selected { background: #f0fdfa !important; }
.site-row.selected td { color: var(--primary); }

.asset-chk {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Summary Items */
.stat-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 700; color: #475569; }
.summary-box { background: linear-gradient(to bottom right, #ffffff, #f8fafc); }
.grand-total { border-top: 2px solid #e2e8f0; padding-top: 1rem; margin-top: 0.5rem; }

/* Badges */
.badge-media { background: #eff6ff; color: #1e40af; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800; border: 1px solid #dbeafe; }
.badge-ha { background: #dcfce7; color: #166534; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }
.badge-ta { background: #fef9c3; color: #854d0e; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; }

.status-available { background: #ecfdf5; color: #059669; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 800; border: 1px solid #d1fae5; }
.site-thumb { width: 140px; height: 90px; border-radius: 8px; object-fit: cover; cursor: zoom-in; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.no-img { font-size: 0.7rem; color: #94a3b8; font-style: italic; }

.sno-cell { font-weight: 800; color: #94a3b8; text-align: center; font-size: 0.85rem; }

/* Pagination Styles */
.pagination-wrap { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem; background: #fafafa; border-top: 1px solid #f1f5f9; border-radius: 0 0 20px 20px; }
.pg-info { font-size: 0.85rem; color: #64748b; font-weight: 600; }
.pg-controls { display: flex; gap: 0.4rem; align-items: center; }
.pg-btn { min-width: 38px; height: 38px; border: 1px solid #e2e8f0; background: white; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 0.85rem; transition: all 0.2s; color: #475569; display: flex; align-items: center; justify-content: center; }
.pg-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: #f0fdfa; transform: translateY(-1px); }
.pg-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2); }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; background: #f8fafc; }
.pg-dots { color: #94a3b8; font-weight: 800; padding: 0 0.5rem; }

/* Responsive */
@media (max-width: 1400px) {
    .proposal-bottom-grid { grid-template-columns: repeat(3, 1fr); gap: 1rem; }
}

@media (max-width: 1200px) {
    .proposal-bottom-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .proposal-bottom-grid { grid-template-columns: 1fr; }
    .p-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
}

/* Modal Styles */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 5000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
.modal-content { background: white; width: 95%; max-width: 550px; border-radius: 30px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
.modal-header { padding: 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 2.5rem; }
.modal-footer { padding: 1.5rem 2.5rem; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 1rem; }
</style>

<script>
let selectedSites = [];
let currentPage = 1;
let pageSize = 10;

function handleClientChange() {
    const select = document.getElementById('client_id');
    const contact = select.options[select.selectedIndex].dataset.contact;
    document.getElementById('contact_person').value = contact || '';
}

function calculateEndDate() {
    const startStr = document.getElementById('start_date').value;
    const days = parseInt(document.getElementById('total_days').value);
    
    if (startStr && !isNaN(days) && days > 0) {
        const startDate = new Date(startStr);
        const endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + days - 1);
        document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
    }
}

function calculateTotalDays() {
    const startStr = document.getElementById('start_date').value;
    const endStr = document.getElementById('end_date').value;
    
    if (startStr && endStr) {
        const start = new Date(startStr);
        const end = new Date(endStr);
        const diffTime = end - start;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('total_days').value = diffDays > 0 ? diffDays : 0;
    }
}

function renderPagination() {
    const allRows = Array.from(document.querySelectorAll('#asset-body tr.site-row'));
    const activeRows = allRows.filter(row => !row.classList.contains('search-hidden'));
    
    const total = activeRows.length;
    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, total);
    
    // Hide everything first
    allRows.forEach(row => row.style.display = 'none');

    // Update S.No for all active rows (sequential 1 to N)
    activeRows.forEach((row, index) => {
        const snoCell = row.querySelector('.sno-cell');
        if(snoCell) snoCell.innerText = index + 1;
    });

    // Show only current page slice
    const visibleRows = activeRows.slice(start, end);
    visibleRows.forEach(row => row.style.display = '');

    // Update info
    document.getElementById('pg-start').innerText = total === 0 ? 0 : start + 1;
    document.getElementById('pg-end').innerText = end;
    document.getElementById('pg-total').innerText = total;

    updatePgControls(total);
}

function updatePgControls(total) {
    const totalPages = Math.ceil(total / pageSize);
    const container = document.getElementById('pg-numbers');
    container.innerHTML = '';

    if (totalPages <= 1) return;

    const createBtn = (content, disabled, active, onClick) => {
        const btn = document.createElement('button');
        btn.className = 'pg-btn' + (active ? ' active' : '');
        btn.innerHTML = content;
        btn.disabled = disabled;
        if (!disabled) btn.onclick = onClick;
        return btn;
    };

    // First & Prev
    container.appendChild(createBtn('<i class="fas fa-angle-double-left"></i>', currentPage === 1, false, () => { currentPage = 1; renderPagination(); }));
    container.appendChild(createBtn('<i class="fas fa-angle-left"></i>', currentPage === 1, false, () => { currentPage--; renderPagination(); }));

    // Page Numbers
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    if (startPage > 1) {
        container.appendChild(createBtn('1', false, false, () => { currentPage = 1; renderPagination(); }));
        if (startPage > 2) {
            const dots = document.createElement('span');
            dots.className = 'pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        container.appendChild(createBtn(i, false, i === currentPage, () => { currentPage = i; renderPagination(); }));
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dots = document.createElement('span');
            dots.className = 'pg-dots';
            dots.innerText = '...';
            container.appendChild(dots);
        }
        container.appendChild(createBtn(totalPages, false, false, () => { currentPage = totalPages; renderPagination(); }));
    }

    // Next & Last
    container.appendChild(createBtn('<i class="fas fa-angle-right"></i>', currentPage === totalPages, false, () => { currentPage++; renderPagination(); }));
    container.appendChild(createBtn('<i class="fas fa-angle-double-right"></i>', currentPage === totalPages, false, () => { currentPage = totalPages; renderPagination(); }));
}

function changePageSize() {
    pageSize = parseInt(document.getElementById('pg-limit').value);
    currentPage = 1;
    renderPagination();
}

function openClientModal() { document.getElementById('clientModal').style.display = 'flex'; }
function closeClientModal() { document.getElementById('clientModal').style.display = 'none'; }

function submitQuickClient() {
    const name = document.getElementById('new_client_name').value;
    const contact = document.getElementById('new_client_contact').value;
    const phone = document.getElementById('new_client_phone').value;
    const email = document.getElementById('new_client_email').value;
    const city = document.getElementById('new_client_city').value;
    const address = document.getElementById('new_client_address').value;

    if (!name) {
        Swal.fire('Error', 'Company Name is required', 'error');
        return;
    }

    const data = { type: 'client', name, contact, phone, email, city, address };

    fetch('../../ajax/quick_save_partner.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const select = document.getElementById('client_id');
            const opt = document.createElement('option');
            opt.value = res.id;
            opt.text = name;
            opt.selected = true;
            select.add(opt);
            closeClientModal();
            Swal.fire('Success', 'Client created and selected!', 'success');
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function toggleSite(id) {
    const row = document.getElementById('row-' + id);
    const chk = row.querySelector('.asset-chk');
    const input = row.querySelector('.sale-rate-input');
    
    const name = row.dataset.name;
    const rate = parseFloat(row.dataset.rate);
    const prate = parseFloat(row.dataset.prate);
    const owner = row.dataset.owner;
    const sqft = parseFloat(row.dataset.sqft);

    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx === -1) {
        selectedSites.push({ id, name, cardRate: rate, purchaseRate: prate, saleRate: rate, owner, sqft });
        row.classList.add('selected');
        chk.checked = true;
        input.disabled = false;
    } else {
        selectedSites.splice(idx, 1);
        row.classList.remove('selected');
        chk.checked = false;
        input.disabled = true;
        row.querySelector('.total-cell').innerText = '₹0';
        row.querySelector('.markup-cell').innerText = '-';
    }
    
    const count = selectedSites.length;
    if(document.getElementById('selected-count')) document.getElementById('selected-count').innerText = count;
    if(document.getElementById('selected-count-btm')) document.getElementById('selected-count-btm').innerText = count;
    recalcAll();
}

function updateSitePrice(id, val) {
    const idx = selectedSites.findIndex(s => s.id === id);
    if (idx !== -1) {
        selectedSites[idx].saleRate = parseFloat(val) || 0;
        recalcAll();
    }
}

function recalcAll() {
    const globalDisc = parseFloat(document.getElementById('global_discount').value) || 0;
    const globalMark = parseFloat(document.getElementById('global_markup').value) || 0;
    const print = parseFloat(document.getElementById('print_cost').value) || 0;
    const mount = parseFloat(document.getElementById('mount_cost').value) || 0;
    const taxType = document.getElementById('tax-type').value;

    let totalDisplay = 0;

    selectedSites.forEach((site) => {
        const row = document.getElementById('row-' + site.id);
        
        // If global pricing is used, we could auto-adjust saleRate
        // For now, we'll treat them as adjustments to the total or allow them to drive individual rates
        // Let's make them drive the saleRate if the user inputs a global value
        // But to keep it "proper", we'll apply them to the base cardRate if not manually overridden
        
        const currentTotal = site.saleRate;
        
        // Margin Analysis: (Sale - Purchase) / Purchase * 100
        const markupVal = site.saleRate - site.purchaseRate;
        const markupPct = site.purchaseRate > 0 ? ((markupVal / site.purchaseRate) * 100).toFixed(1) : '0';

        totalDisplay += currentTotal;
        
        // Update row visual
        if(row) {
            row.querySelector('.total-cell').innerText = '₹' + currentTotal.toLocaleString();
        }
    });

    // Apply global discount/markup to the total display cost
    let adjustedDisplay = totalDisplay;
    if (globalMark > 0) adjustedDisplay += (totalDisplay * (globalMark / 100));
    if (globalDisc > 0) adjustedDisplay -= (totalDisplay * (globalDisc / 100));

    const subtotal = adjustedDisplay + print + mount;
    const totalTax = subtotal * 0.18;
    const grand = subtotal + totalTax;

    // Update Summary Panel
    document.getElementById('sum-display-btm').innerText = '₹' + adjustedDisplay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Tax Breakdown Logic
    const taxContainer = document.getElementById('tax-breakdown');
    if (taxType === 'cgst_sgst') {
        const halfTax = totalTax / 2;
        taxContainer.innerHTML = `
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span style="color:#64748b; font-weight:600;">CGST (9%):</span>
                <span style="font-weight:800;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span style="color:#64748b; font-weight:600;">SGST (9%):</span>
                <span style="font-weight:800;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        `;
    } else {
        taxContainer.innerHTML = `
            <div class="stat-row" style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span style="color:#64748b; font-weight:600;">IGST (18%):</span>
                <span style="font-weight:800;">₹${totalTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        `;
    }

    document.getElementById('sum-grand-btm').innerText = '₹' + grand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function filterSites() {
    const q = document.getElementById('site-search').value.toLowerCase();
    const ownership = document.querySelector('input[name="ownership"]:checked').value;
    const availability = document.querySelector('input[name="availability"]:checked').value;
    
    const genre = document.getElementById('filter-genre').value;
    const type = document.getElementById('filter-type').value;
    const state = document.getElementById('filter-state').value;
    const city = document.getElementById('filter-city').value;
    const illumination = document.getElementById('filter-illumination').value;

    const rows = document.querySelectorAll('#asset-body tr.site-row');
    
    rows.forEach(row => {
        let show = true;
        
        // Ownership Filter
        if (ownership !== 'all' && row.dataset.owner !== ownership) show = false;
        
        // Availability Filter
        if (availability === 'available' && row.dataset.status !== 'available') show = false;
        
        // Select Filters
        if (genre && row.dataset.genre !== genre) show = false;
        if (type && row.dataset.type !== type) show = false;
        if (state && row.dataset.state !== state) show = false;
        if (city && row.dataset.city !== city) show = false;
        if (illumination && row.dataset.illumination !== illumination) show = false;
        
        // Text Search
        if (q) {
            const rowText = (row.dataset.name + ' ' + row.dataset.code + ' ' + row.dataset.location + ' ' + row.dataset.city).toLowerCase();
            if (!rowText.includes(q)) show = false;
        }

        if (show) {
            row.classList.remove('search-hidden');
        } else {
            row.classList.add('search-hidden');
        }
    });

    currentPage = 1;
    renderPagination();
}

document.addEventListener('DOMContentLoaded', () => {
    renderPagination();
});

function saveProposal() {
    const clientId = document.getElementById('client_id').value;
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    const campaignName = document.getElementById('campaign_name').value;
    const deliveryDate = document.getElementById('delivery_date').value;
    const totalDays = document.getElementById('total_days').value;
    const remark = document.getElementById('remark').value;
    const contactPerson = document.getElementById('contact_person').value;
    
    if (!clientId || !start || !end || !campaignName || selectedSites.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select a client, set the dates, enter campaign name, and select at least one asset.',
            confirmButtonColor: 'var(--primary)'
        });
        return;
    }

    const data = {
        clientId,
        startDate: start,
        endDate: end,
        campaignName,
        deliveryDate,
        totalDays,
        remark,
        contactPerson,
        printCost: parseFloat(document.getElementById('print_cost').value) || 0,
        mountCost: parseFloat(document.getElementById('mount_cost').value) || 0,
        selectedSites
    };

    fetch('../../ajax/save_proposal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('Success', 'Proposal generated successfully!', 'success')
            .then(() => window.location.href = '<?php echo BASE_URL; ?>modules/proposals/proposals.php');
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function goToStep2() {
    const clientId = document.getElementById('client_id').value;
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    
    if (!clientId || !start || !end) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Details',
            text: 'Please select a client and set both start and end dates before proceeding.',
            confirmButtonColor: 'var(--primary)'
        });
        return;
    }
    
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'block';
    
    // Step 1 styling -> Completed
    const c1 = document.querySelector('#step-tab-1 .step-circle');
    c1.innerHTML = '<i class="fas fa-check"></i>';
    c1.style.background = 'var(--primary)';
    c1.style.color = 'white';
    c1.style.boxShadow = '0 0 0 3px var(--primary)';
    
    // Step 2 styling -> Active
    const c2 = document.querySelector('#step-tab-2 .step-circle');
    c2.style.background = 'var(--primary)';
    c2.style.color = 'white';
    c2.style.boxShadow = '0 0 0 3px var(--primary)';
    
    document.querySelector('#step-tab-2 .step-label').style.color = 'var(--primary)';
    document.querySelector('#step-tab-2 .step-label').style.fontWeight = '800';
    
    // Progress Line
    document.getElementById('wizard-progress-line').style.width = '100%';
}

function goToStep1() {
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
    
    // Step 1 styling -> Active
    const c1 = document.querySelector('#step-tab-1 .step-circle');
    c1.innerHTML = '1';
    
    // Step 2 styling -> Inactive
    const c2 = document.querySelector('#step-tab-2 .step-circle');
    c2.style.background = 'white';
    c2.style.color = '#94a3b8';
    c2.style.boxShadow = '0 0 0 3px #e2e8f0';
    
    document.querySelector('#step-tab-2 .step-label').style.color = '#94a3b8';
    document.querySelector('#step-tab-2 .step-label').style.fontWeight = '700';
    
    // Progress Line
    document.getElementById('wizard-progress-line').style.width = '0%';
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
