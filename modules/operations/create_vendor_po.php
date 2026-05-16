<?php
$activePage = 'direct_booking';
$pageTitle = 'Create Direct Booking';
$hideSidebar = true;
include_once __DIR__ . '/../../includes/header.php';

// Fetch initial data for filters only
$clients = $pdo->query("SELECT id, name, city, contact_person FROM partners WHERE type = 'client' ORDER BY name ASC")->fetchAll();
$vendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();

$cities = $pdo->query("SELECT DISTINCT city FROM sites WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$states = $pdo->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
$mediaTypes = $pdo->query("SELECT DISTINCT type FROM sites WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$illuminations = $pdo->query("SELECT DISTINCT light_type FROM sites WHERE light_type IS NOT NULL AND light_type != '' ORDER BY light_type")->fetchAll(PDO::FETCH_COLUMN);
$sizes = $pdo->query("SELECT DISTINCT CONCAT(width, 'x', height) as size FROM sites WHERE width IS NOT NULL AND height IS NOT NULL AND width != '' AND height != '' ORDER BY width, height")->fetchAll(PDO::FETCH_COLUMN);
$printingVendors = $pdo->query("SELECT id, name FROM partners WHERE type = 'vendor' ORDER BY name ASC")->fetchAll();
$printingRates = $pdo->query("SELECT * FROM vendor_printing_rates")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="proposal-full-wrapper">
    <!-- Wizard Progress Tracker -->
    <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 1.5rem; background: white; padding: 0.6rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); max-width: 400px; margin-left: auto; margin-right: auto;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div id="step-tab-1" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                <div class="step-circle" style="width: 24px; height: 24px; background: #059669; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 0 0 2px #059669;"><i class="fas fa-check"></i></div>
                <span class="step-label" style="font-size: 0.55rem; font-weight: 800; color: #059669; text-transform: uppercase;">Details</span>
            </div>
            <div style="width: 30px; height: 2px; background: #e2e8f0; position: relative; margin-top: -12px;">
                <div id="wizard-progress-line" style="position: absolute; left: 0; top: 0; height: 100%; width: 0%; background: #059669; transition: width 0.4s;"></div>
            </div>
            <div id="step-tab-2" style="display: flex; flex-direction: column; align-items: center; gap: 0.2rem;">
                <div class="step-circle" style="width: 24px; height: 24px; background: #fff; color: #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; border: 2px solid white; box-shadow: 0 0 0 2px #e2e8f0;">2</div>
                <span class="step-label" style="font-size: 0.55rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Assets</span>
            </div>
        </div>
    </div>

    <!-- STEP 1 -->
    <div id="step-1">
        <div class="p-panel" style="max-width: 1100px; margin: 0 auto 1.5rem auto;">
            <div class="p-header">Campaign Details & Duration</div>
            
            <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-bottom: 1rem;">
                <div class="form-group">
                    <label>Campaign Name <span style="color:red;">*</span></label>
                    <input type="text" id="campaign_name" class="p-input" placeholder="e.g. Summer Sale 2024" style="height: 38px;" required>
                </div>
                <div class="form-group">
                    <label style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Company / Client <span style="color:red;">*</span></span>
                        <button type="button" class="btn-text" onclick="openClientModal()" style="font-size: 0.7rem; color: var(--primary); background: none; border: none; cursor: pointer; padding: 0;">
                            <i class="fas fa-plus-circle"></i> New
                        </button>
                    </label>
                    <select id="client_id" class="p-input" style="height: 38px;" onchange="handleClientChange()" required>
                        <option value="">-- Choose Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-contact="<?php echo htmlspecialchars($c['contact_person'] ?? ''); ?>">
                                <?php echo $c['name']; ?> <?php echo $c['city'] ? "({$c['city']})" : ""; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- GST Selection for Group Companies -->
                <div id="gst_selection_container" style="display: none; grid-column: span 3; margin-top: 0.5rem; background: #f0fdfa; padding: 1rem; border-radius: 12px; border: 1px solid #ccfbf1; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                        <label style="color: var(--primary); font-weight: 800; font-size: 0.7rem; margin-bottom: 0; display: block; text-transform: uppercase;">
                            <i class="fas fa-id-card"></i> Billing GSTIN / State Selection
                        </label>
                        <span id="gst_count_badge" style="background: var(--primary); color: white; font-size: 0.65rem; padding: 2px 8px; border-radius: 50px; font-weight: 700;"></span>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <select id="selected_gstin" class="p-input" style="flex: 1; height: 38px; border-color: #5eead4; background: white;" onchange="handleGstSelectionChange()">
                            <!-- Dynamic Options -->
                        </select>
                        <div id="gst_details_preview" style="flex: 2; background: white; border: 1px solid #ccfbf1; border-radius: 6px; padding: 0.5rem; font-size: 0.75rem; color: #0f766e; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-map-marker-alt"></i>
                            <span id="gst_preview_text">Select a GSTIN to see location details</span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" id="contact_person" class="p-input" placeholder="Full Name" style="height: 38px;">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                <div class="form-group">
                    <label>From Date <span style="color:red;">*</span></label>
                    <input type="date" id="start_date" class="p-input" style="height: 38px;" value="<?php echo date('Y-m-d'); ?>" onchange="calculateEndDate()" required>
                </div>
                <div class="form-group">
                    <label>To Date <span style="color:red;">*</span></label>
                    <input type="date" id="end_date" class="p-input" style="height: 38px;" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>" onchange="calculateTotalDays()" required>
                </div>
                <div class="form-group">
                    <label>Total Days</label>
                    <input type="number" id="total_days" class="p-input" placeholder="Days" style="height: 38px;" oninput="calculateEndDate()">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1.5rem;">
                <label>Internal Remarks</label>
                <textarea id="remark" class="p-input" rows="2" placeholder="Notes for this booking..."></textarea>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin: 2rem auto; max-width: 1100px;">
            <button class="btn btn-primary" onclick="goToStep2()" style="width: 250px; height: 48px; border-radius: 12px; font-weight: 800; font-size: 0.95rem;">
                Next Step: Select Assets <i class="fas fa-arrow-right" style="margin-left: 0.75rem;"></i>
            </button>
        </div>
    </div>

    <!-- STEP 2 -->
    <div id="step-2" style="display: none;">
        <div class="p-panel" style="margin-bottom: 1rem; padding: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button onclick="goToStep1()" class="btn btn-secondary" style="height: 38px; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 800; padding: 0 1.2rem; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <h3 style="margin: 0; color: #0d9488; font-weight: 800; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">Search Criteria</h3>
                </div>
                <div style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Step 2: Assets & Strategy</div>
            </div>

            <div style="display: flex; gap: 3rem; margin-bottom: 1rem; align-items: center;">
                <div class="search-group">
                    <label style="font-size: 0.6rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Ownership</label>
                    <div class="radio-group" style="gap: 1.5rem; display: flex;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;"><input type="radio" name="ownership" value="all" checked onchange="fetchSites(1)" style="width: 18px; height: 18px; accent-color: #0d9488;"> All</label>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;"><input type="radio" name="ownership" value="HA" onchange="fetchSites(1)" style="width: 18px; height: 18px; accent-color: #0d9488;"> Self</label>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;"><input type="radio" name="ownership" value="TA" onchange="fetchSites(1)" style="width: 18px; height: 18px; accent-color: #0d9488;"> Vendor</label>
                    </div>
                </div>
                <div class="search-group">
                    <label style="font-size: 0.6rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Availability</label>
                    <div class="radio-group" style="gap: 1.5rem; display: flex;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;"><input type="radio" name="availability" value="available" checked onchange="fetchSites(1)" style="width: 18px; height: 18px; accent-color: #0d9488;"> Available</label>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;"><input type="radio" name="availability" value="all" onchange="fetchSites(1)" style="width: 18px; height: 18px; accent-color: #0d9488;"> All</label>
                    </div>
                </div>
                <div id="vendor-filter-group" class="search-group" style="display: none; min-width: 200px;">
                    <label style="font-size: 0.6rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Vendor</label>
                    <select id="filter-vendor" class="p-input" onchange="fetchSites(1)" style="height: 38px; font-size: 0.85rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;">
                        <option value="">All Vendors</option>
                        <?php foreach($vendors as $v): ?> <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option> <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2.5fr 1fr 1fr 1fr 1fr 1fr; gap: 0.75rem; align-items: flex-end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.4rem; display: block;">Search Site / Code / Area</label>
                    <input type="text" id="site-search" class="p-input" placeholder="Search by Site Name, Code, Location, City, State..." oninput="fetchSites(1)" style="height: 38px; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.4rem; display: block;">Media</label>
                    <select id="media_type" class="p-input" onchange="fetchSites(1)" style="height: 38px; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="">All</option>
                        <?php foreach($mediaTypes as $mt): ?> <option value="<?php echo $mt; ?>"><?php echo $mt; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.4rem; display: block;">State</label>
                    <select id="filter-state" class="p-input" onchange="fetchSites(1)" style="height: 38px; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="">All</option>
                        <?php foreach($states as $s): ?> <option value="<?php echo $s; ?>"><?php echo $s; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.4rem; display: block;">City</label>
                    <select id="filter-city" class="p-input" onchange="fetchSites(1)" style="height: 38px; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="">All</option>
                        <?php foreach($cities as $c): ?> <option value="<?php echo $c; ?>"><?php echo $c; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.4rem; display: block;">Light</label>
                    <select id="light_type" class="p-input" onchange="fetchSites(1)" style="height: 38px; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="">All</option>
                        <?php foreach($illuminations as $il): ?> <option value="<?php echo $il; ?>"><?php echo $il; ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.55rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.4rem; display: block;">Size</label>
                    <select id="filter-size" class="p-input" onchange="fetchSites(1)" style="height: 38px; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="">All</option>
                        <?php foreach($sizes as $sz): ?> <option value="<?php echo $sz; ?>"><?php echo $sz; ?></option> <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="p-panel" id="asset-plan-panel" style="margin-bottom: 2rem;">
            <div class="p-header" style="display: flex; justify-content: space-between; align-items: center;">
                <span>Select Assets</span>
                <button onclick="openBucket()" style="background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; padding: 0.4rem 1rem; border-radius: 8px; font-weight: 800; font-size: 0.8rem; cursor: pointer;">
                    Selected: <span id="selected-count">0</span>
                </button>
            </div>

            <div style="min-height: 400px; position: relative;">
                <div id="loading-overlay" style="display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.7); z-index: 20; align-items: center; justify-content: center; flex-direction: column;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                    <span style="margin-top: 1rem; font-weight: 700; color: #64748b;">Loading Sites...</span>
                </div>
                <table class="crs-table selection-table" style="width: 100%; border-collapse: separate; border-spacing: 0 0.5rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9;">
                            <th style="width: 40px; padding: 0.8rem 1rem;">#</th>
                            <th style="width: 50px; padding: 0.8rem 1rem; text-align:center;"><i class="far fa-check-square"></i></th>
                            <th style="width: 100px; padding: 0.8rem 1rem;">PREVIEW</th>
                            <th style="padding: 0.8rem 1rem;">CITY / CODE</th>
                            <th style="padding: 0.8rem 1rem;">ASSET DETAILS</th>
                            <th style="padding: 0.8rem 1rem;">SIZE</th>
                            <th style="padding: 0.8rem 1rem; text-align:right;">OFFER RATE</th>
                            <th style="padding: 0.8rem 1rem; text-align: right;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody id="asset-body">
                        <!-- Dynamic Content -->
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap" style="padding: 1rem; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                <div id="pg-info" style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Showing 0 to 0 of 0 sites</div>
                <div id="pg-numbers" style="display: flex; gap: 0.25rem;"></div>
            </div>
        </div>

        <div class="proposal-action-bar" style="position: sticky; bottom: 0; background: white; border-top: 2px solid var(--primary); padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; z-index: 1000; border-radius: 12px 12px 0 0; box-shadow: 0 -10px 25px rgba(0,0,0,0.05);">
            <div style="display: flex; gap: 1.5rem; align-items: center; width: 100%; justify-content: flex-end;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-right: 1rem;">
                    <label style="font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Tax Type</label>
                    <select id="tax-type" class="p-input" onchange="recalcAll()" style="width: 110px; height: 32px; font-size: 0.7rem; padding: 0 0.4rem; border-radius: 6px;">
                        <option value="igst">IGST 18%</option>
                        <option value="cgst_sgst">CGST/SGST</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.6rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Subtotal</div>
                    <div id="sum-display-btm" style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">₹0</div>
                </div>
                <div style="text-align: right;" id="tax-breakdown-container">
                    <div style="font-size: 0.6rem; color: #64748b; font-weight: 800; text-transform: uppercase;">GST (18%)</div>
                    <div id="sum-tax-btm" style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">₹0</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.6rem; color: var(--primary); font-weight: 900; text-transform: uppercase;">Grand Total</div>
                    <div id="sum-grand-btm" style="font-size: 1.3rem; font-weight: 900; color: var(--primary);">₹0</div>
                </div>
                <button class="btn btn-primary" onclick="saveDirectBooking()" id="submitBtn" style="height: 42px; padding: 0 1.5rem; border-radius: 8px; font-weight: 900;">
                    GENERATE BOOKING
                </button>
            </div>
        </div>
    </div>

    <!-- Bucket Drawer -->
    <div id="bucket-backdrop" onclick="closeBucket()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 2000; display: none;"></div>
    <div id="selection-bucket-panel" style="position: fixed; top: 0; right: -1400px; width: 1200px; max-width: 95vw; height: 100%; background: white; z-index: 2001; box-shadow: -10px 0 30px rgba(0,0,0,0.1); transition: all 0.4s; display: flex; flex-direction: column;">
        <div class="p-header" style="padding: 1.5rem; background: var(--primary); color: white; margin: 0; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 1rem;"><i class="fas fa-shopping-basket"></i> Review Selected Assets</div>
            <button onclick="closeBucket()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div id="bucket-list" style="flex: 1; overflow-y: auto; padding: 1rem;"></div>
        <div style="padding: 1rem; border-top: 1px solid #e2e8f0; background: #f8fafc;">
            <button onclick="closeBucket()" class="btn btn-primary" style="width: 100%; height: 45px; border-radius: 10px; font-weight: 800;">CONTINUE SELECTION</button>
        </div>
    </div>
</div>

<div id="clientModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 5000; align-items: center; justify-content: center;">
    <div class="card" style="width: 90%; max-width: 500px; padding: 2rem; border-radius: 20px;">
        <h3 style="margin-top: 0; font-weight: 800; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">New Client</h3>
        <div class="form-group" style="margin-bottom: 1rem;"><label>Company Name</label><input type="text" id="new_client_name" class="p-input"></div>
        <div class="form-group" style="margin-bottom: 1rem;"><label>Contact Person</label><input type="text" id="new_client_contact" class="p-input"></div>
        <div class="form-group" style="margin-bottom: 1.5rem;"><label>City</label><input type="text" id="new_client_city" class="p-input"></div>
        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <button class="btn" onclick="closeClientModal()">Cancel</button>
            <button class="btn btn-primary" onclick="submitQuickClient()">Save & Select</button>
        </div>
    </div>
</div>

<style>
.proposal-full-wrapper { padding: 2rem; background: #f8fafc; min-height: 100vh; }
.p-panel { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.p-header { font-weight: 800; font-size: 0.95rem; color: var(--primary); margin-bottom: 1.25rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem; }
.form-grid { display: grid; gap: 1rem; }
.form-group label { display: block; font-size: 0.7rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.4rem; text-transform: uppercase; }
.p-input { width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; font-size: 0.85rem; }
.crs-table th { font-size: 0.65rem; color: #64748b; text-transform: uppercase; padding: 1rem; text-align: left; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.crs-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; vertical-align: middle; }
.site-row.selected { background: #f0fdfa !important; }
.pg-btn { min-width: 32px; height: 32px; border: 1px solid #e2e8f0; background: white; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 0.75rem; }
.pg-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
</style>

<script>
let selectedSites = [];
let currentPage = 1;
let totalSites = 0;
const pageSize = 6;
const baseUrl = "<?php echo BASE_URL; ?>";
const imgBaseUrl = "../../uploads/sites/";
const printingVendors = <?php echo json_encode($printingVendors); ?>;
const printingRates = <?php echo json_encode($printingRates); ?>;

function getBestPrintingRate(siteId, mediaType) {
    let rate = printingRates.find(r => r.site_id == siteId);
    if (rate) return rate;
    rate = printingRates.find(r => !r.site_id && r.media_type === mediaType);
    return rate || null;
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
        const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('total_days').value = diffDays > 0 ? diffDays : 0;
    }
}

function handleClientChange() {
    const select = document.getElementById('client_id');
    const clientId = select.value;
    const contact = select.options[select.selectedIndex].dataset.contact;
    document.getElementById('contact_person').value = contact || '';

    const gstContainer = document.getElementById('gst_selection_container');
    const gstSelect = document.getElementById('selected_gstin');
    const gstBadge = document.getElementById('gst_count_badge');
    const gstPreview = document.getElementById('gst_preview_text');

    if (!clientId) {
        gstContainer.style.display = 'none';
        return;
    }

    // Fetch Full Client Details via AJAX
    fetch(`../../ajax/get_partner_details.php?id=${clientId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const p = res.data;
            gstSelect.innerHTML = '';
            
            // Show for all clients who have GST info, but following proposal creator logic:
            if (p.business_type !== 'Group of Companies') {
                gstContainer.style.display = 'none';
                return;
            }

            let gsts = [];
            // Add primary GST if exists
            if (p.gstin) {
                gsts.push({ gstin: p.gstin, state: 'Primary', city: '', district: '', address: 'Main Address' });
            }

            // Parse additional GSTs
            if (p.additional_gst) {
                try {
                    const extra = JSON.parse(p.additional_gst);
                    if (Array.isArray(extra)) {
                        gsts = gsts.concat(extra);
                    } else if (typeof extra === 'object') {
                        gsts = gsts.concat(Object.values(extra));
                    }
                } catch(e) { console.error("GST Parse Error", e); }
            }

            if (gsts.length > 0) {
                gstContainer.style.display = 'grid';
                gstBadge.innerText = `${gsts.length} GST Records Found`;
                
                gsts.forEach((g, idx) => {
                    const opt = document.createElement('option');
                    opt.value = g.gstin;
                    opt.text = `${g.gstin} - ${g.state || ''} ${g.city ? '(' + g.city + ')' : ''}`;
                    opt.dataset.details = JSON.stringify(g);
                    gstSelect.add(opt);
                });
                
                handleGstSelectionChange(); // Update preview for first option
            } else {
                gstContainer.style.display = 'none';
            }
        } else {
            gstContainer.style.display = 'none';
        }
    });
}

function handleGstSelectionChange() {
    const select = document.getElementById('selected_gstin');
    const preview = document.getElementById('gst_preview_text');
    
    if (select.selectedIndex === -1) return;
    
    const data = JSON.parse(select.options[select.selectedIndex].dataset.details);
    let text = "";
    if (data.address) text += data.address;
    if (data.city) text += (text ? ", " : "") + data.city;
    if (data.state) text += (text ? ", " : "") + data.state;
    
    preview.innerText = text || "No specific location details";
}

function goToStep1() {
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
    document.getElementById('wizard-progress-line').style.width = '0%';
}

function goToStep2() {
    const client = document.getElementById('client_id').value;
    const campaign = document.getElementById('campaign_name').value;
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;

    if (!client || !campaign || !start || !end) {
        return Swal.fire('Required', 'Please fill Campaign Name, Client, and Booking Dates.', 'warning');
    }
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'block';
    document.getElementById('wizard-progress-line').style.width = '100%';
    fetchSites(1);
}

function fetchSites(page = 1) {
    currentPage = page;
    const overlay = document.getElementById('loading-overlay');
    overlay.style.display = 'flex';

    const q = document.getElementById('site-search').value;
    const media = document.getElementById('media_type').value;
    const state = document.getElementById('filter-state').value;
    const city = document.getElementById('filter-city').value;
    const ownership = document.querySelector('input[name="ownership"]:checked').value;
    const availability = document.querySelector('input[name="availability"]:checked').value;
    const vendor = document.getElementById('filter-vendor').value;
    const size = document.getElementById('filter-size').value;
    const light = document.getElementById('light_type').value;

    // Show/Hide Vendor Filter Group
    const vendorGroup = document.getElementById('vendor-filter-group');
    if (vendorGroup) {
        vendorGroup.style.display = (ownership === 'TA') ? 'block' : 'none';
    }

    const url = `../../ajax/fetch_sites.php?page=${page}&limit=${pageSize}&q=${encodeURIComponent(q)}&media=${encodeURIComponent(media)}&state=${encodeURIComponent(state)}&city=${encodeURIComponent(city)}&availability=${availability}&ownership=${ownership}&vendor=${vendor}&size=${encodeURIComponent(size)}&light=${encodeURIComponent(light)}`;

    fetch(url)
    .then(r => r.json())
    .then(res => {
        overlay.style.display = 'none';
        if (res.success) {
            totalSites = res.total;
            renderSites(res.sites);
            renderPagination(res.total);
        }
    });
}

function renderSites(sites) {
    const body = document.getElementById('asset-body');
    body.innerHTML = '';
    
    if (sites.length === 0) {
        body.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:3rem; color:#94a3b8;">No sites found matching criteria.</td></tr>';
        return;
    }

    sites.forEach((s, i) => {
        const selectedSite = selectedSites.find(ss => ss.id == s.id);
        const isSelected = !!selectedSite;
        const currentRate = isSelected ? selectedSite.rate : parseFloat(s.purchase_rate);
        
        const thumb = s.thumbnail ? imgBaseUrl + s.thumbnail : 'https://via.placeholder.com/150x95?text=No+Img';
        const imgList = (s.all_images || "").split(',').filter(img => img.trim() !== "");
        const imgCount = imgList.length;
        const startIdx = (currentPage - 1) * pageSize + i + 1;
        const cardRate = parseFloat(s.card_rate || 0);
        
        const previewHtml = s.thumbnail 
            ? `<div style="position: relative; width: 150px; height: 95px;">
                    <img src="${thumb}" onclick="openLightboxSlider('${s.all_images || ''}', ${s.id})" 
                         style="width: 100%; height: 100%; border-radius: 12px; object-fit: cover; border: 1px solid ${isSelected ? '#059669' : '#e2e8f0'}; cursor: pointer; transition: transform 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    ${imgCount > 1 ? `
                        <div style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.8); color: white; font-size: 0.65rem; padding: 3px 8px; border-radius: 6px; font-weight: 800; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);">
                            <i class="fas fa-images"></i> ${imgCount}
                        </div>
                    ` : ''}
               </div>`
            : `<div style="width: 150px; height: 95px; border-radius: 12px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: #94a3b8; font-weight: 700;">No Image</div>`;

        const row = document.createElement('tr');
        row.className = 'site-row' + (isSelected ? ' selected' : '');
        row.id = 'row-' + s.id;
        row.style.background = 'white';
        row.innerHTML = `
            <td style="font-weight:700; color:#64748b; padding:1rem;">${startIdx}</td>
            <td style="text-align:center; padding:1rem;"><input type="checkbox" ${isSelected ? 'checked' : ''} onclick="toggleSite(${s.id}, '${s.name.replace(/'/g, "\\'")}', ${currentRate}, '${s.site_code}', '${s.location.replace(/'/g, "\\'")}', ${s.vendor_id}, '${s.thumbnail || ''}', '${s.city || ''}', ${cardRate}, '${s.width}x${s.height}', '${s.type}', '${s.light_type}', '${s.owner_type}', '${s.vendor_name}', '${s.all_images || ''}')" style="width:18px; height:18px; accent-color:var(--primary);"></td>
            <td style="padding:1rem;">${previewHtml}</td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.city || ''}</div>
                <div style="color:#f97316; font-size:0.65rem; font-weight:800; text-transform:uppercase;">${s.site_code}</div>
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.name}</div>
                <div style="font-size:0.65rem; color:#64748b; margin-bottom:4px; line-height:1.1;">${s.location}</div>
                <div style="display:flex; gap:0.3rem; align-items:center;">
                    <span style="background:#ecfdf5; color:#059669; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.type}</span>
                    <span style="background:#f1f5f9; color:#475569; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.light_type}</span>
                    <span style="background:#f1f5f9; color:#475569; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">
                        ${s.owner_type}${s.owner_type === 'TA' && s.vendor_name ? ' - ' + s.vendor_name : ''}
                    </span>
                </div>
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.width}' x ${s.height}'</div>
                <div style="font-size:0.65rem; color:#94a3b8; font-weight:700;">${(s.width*s.height).toLocaleString()} SQFT</div>
            </td>
            <td style="padding:1rem; text-align:right;">
                <div style="font-size:0.65rem; color:var(--primary); font-weight:800; margin-bottom:4px; text-transform:uppercase;">Offer Rate</div>
                <input type="number" class="p-input offer-rate-input" value="${currentRate}" oninput="updateSitePrice(${s.id}, this.value)" style="width:100px; height:32px; text-align:right; font-weight:900; color:var(--primary); padding:0 0.5rem; border-radius:8px; border: 1px solid #e2e8f0;">
            </td>
            <td style="padding:1.5rem 1rem; text-align:right;">
                <div style="font-size:0.65rem; color:#64748b; font-weight:800; margin-bottom:4px; text-transform:uppercase;">Total</div>
                <div class="total-cell" style="font-weight:900; color:var(--primary); font-size:1.1rem;">₹${currentRate.toLocaleString()}</div>
            </td>
        `;
        body.appendChild(row);
    });
}

function toggleSite(id, name, rate, code, location, vendor, thumbnail = '', city = '', card_rate = 0, size = '', type = '', light_type = '', owner_type = '', vendor_name = '', all_images = '') {
    const idx = selectedSites.findIndex(s => s.id == id);
    if (idx === -1) {
        const sqft = parseFloat(size.split('x')[0]) * parseFloat(size.split('x')[1]);
        const bestRate = getBestPrintingRate(id, type);
        const pVendor = bestRate ? bestRate.vendor_id : null;
        const pRate = bestRate ? parseFloat(bestRate.rate_per_sqft) : 0;
        const pTotal = pRate * sqft;

        selectedSites.push({ 
            id, name, rate, code, location, vendor, thumbnail, city, card_rate, size, type, light_type, owner_type, vendor_name, all_images,
            printing_vendor_id: pVendor,
            printing_rate: pRate,
            printing_total: pTotal,
            sqft: sqft
        });
    } else {
        selectedSites.splice(idx, 1);
    }
    document.getElementById('selected-count').innerText = selectedSites.length;
    updateBucketUI();
    recalcAll();
    
    // Update row visual locally
    const row = document.querySelector(`.site-row input[onclick*="toggleSite(${id}"]`)?.closest('tr');
    if (row) {
        row.classList.toggle('selected', idx === -1);
        const chk = row.querySelector('input[type="checkbox"]');
        if (chk) chk.checked = (idx === -1);
    }
}

function updatePrintingInfo(id, vendorId, rateVal) {
    const idx = selectedSites.findIndex(s => s.id == id);
    if (idx !== -1) {
        selectedSites[idx].printing_vendor_id = vendorId;
        selectedSites[idx].printing_rate = parseFloat(rateVal) || 0;
        selectedSites[idx].printing_total = selectedSites[idx].printing_rate * selectedSites[idx].sqft;
        recalcAll();
        updateBucketUI();
    }
}

function updateBucketUI() {
    const list = document.getElementById('bucket-list');
    if (!list) return;

    document.getElementById('selected-count').innerText = selectedSites.length;
    
    if (selectedSites.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:4rem 2rem; color:#94a3b8;"><i class="fas fa-shopping-basket" style="font-size:3rem; opacity:0.2; margin-bottom:1rem; display:block;"></i>No sites selected yet.</div>';
        return;
    }

    let html = `
        <table class="crs-table selection-table" style="width: 100%; border-collapse: separate; border-spacing: 0 0.5rem;">
            <thead>
                <tr style="border-bottom: 2px solid #f1f5f9;">
                    <th style="width: 40px;">#</th>
                    <th style="width: 50px; text-align:center;">ACT</th>
                    <th style="width: 80px;">PREVIEW</th>
                    <th>CITY / CODE</th>
                    <th>ASSET DETAILS</th>
                    <th>SIZE</th>
                    <th>PRINTING</th>
                    <th>PRICING</th>
                    <th style="text-align:right;">OFFER RATE</th>
                    <th style="text-align:right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
    `;

    selectedSites.forEach((s, i) => {
        const rate = parseFloat(s.rate) || 0;
        const thumb = s.thumbnail ? imgBaseUrl + s.thumbnail : 'https://via.placeholder.com/150x95?text=No+Img';
        const cardRate = parseFloat(s.card_rate || 0);
        const imgList = (s.all_images || "").split(',').filter(img => img.trim() !== "");
        const imgCount = imgList.length;

        // Printing Dropdown
        let pOptions = '<option value="">Select Vendor</option>';
        printingVendors.forEach(v => {
            const selected = v.id == s.printing_vendor_id ? 'selected' : '';
            pOptions += `<option value="${v.id}" ${selected}>${v.name}</option>`;
        });

        html += `
            <tr class="site-row selected" style="background: white;">
                <td style="font-weight:700; color:#64748b; padding:1rem;">${i + 1}</td>
                <td style="text-align:center; padding:1rem;">
                    <button onclick="toggleSite(${s.id})" style="background:#fee2e2; color:#ef4444; border:none; width:28px; height:28px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-trash-alt" style="font-size:0.75rem;"></i>
                    </button>
                </td>
                <td style="padding:1rem;">
                    <div style="position: relative; width: 80px; height: 50px;">
                        <img src="${thumb}" onclick="openLightboxSlider('${s.all_images || ''}', ${s.id})" 
                             style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; transition: transform 0.2s;">
                        ${imgCount > 1 ? `<div style="position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,0.8); color: white; font-size: 0.55rem; padding: 2px 5px; border-radius: 4px; font-weight: 800;"><i class="fas fa-images"></i> ${imgCount}</div>` : ''}
                    </div>
                </td>
                <td>
                    <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.city || ''}</div>
                    <div style="color:#f97316; font-size:0.65rem; font-weight:800;">${s.code}</div>
                </td>
                <td>
                    <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.name}</div>
                    <div style="font-size:0.65rem; color:#64748b; margin-bottom:4px; line-height:1.1;">${s.location}</div>
                    <div style="display:flex; gap:0.3rem; align-items:center;">
                        <span style="background:#ecfdf5; color:#059669; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.type}</span>
                    </div>
                </td>
                <td>
                    <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.size}</div>
                    <div style="font-size:0.65rem; color:#94a3b8; font-weight:700;">${s.sqft.toLocaleString()} SQFT</div>
                </td>
                <td>
                    <select onchange="updatePrintingInfo(${s.id}, this.value, document.getElementById('p_rate_${s.id}').value)" 
                            style="width: 120px; font-size: 0.7rem; padding: 0.3rem; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 4px;">
                        ${pOptions}
                    </select>
                    <div style="display: flex; align-items: center; gap: 0.3rem;">
                        <span style="font-size: 0.6rem; color: #64748b;">Rate:</span>
                        <input type="number" id="p_rate_${s.id}" value="${s.printing_rate}" 
                               oninput="updatePrintingInfo(${s.id}, document.getElementById('p_rate_${s.id}').previousElementSibling.previousElementSibling ? document.getElementById('p_rate_${s.id}').previousElementSibling.previousElementSibling.value : '', this.value)"
                               style="width: 60px; height: 24px; font-size: 0.7rem; border-radius: 4px; border: 1px solid #e2e8f0; padding: 0 0.3rem;">
                    </div>
                    <div style="font-size: 0.65rem; font-weight: 800; color: var(--primary); margin-top: 2px;">₹${(s.printing_total || 0).toLocaleString()}</div>
                </td>
                <td>
                    <div style="font-weight:800; color:#64748b; font-size:0.7rem;">CARD: ₹${cardRate.toLocaleString()}</div>
                </td>
                <td style="text-align:right;">
                    <input type="number" class="p-input" value="${rate}" oninput="updateSitePrice(${s.id}, this.value)" style="width:80px; height:32px; text-align:right; font-weight:900; color:var(--primary); padding:0 0.5rem; border-radius:8px; border: 1px solid #e2e8f0;">
                </td>
                <td style="text-align:right;">
                    <div style="font-weight:900; color:var(--primary); font-size:0.95rem;">₹${rate.toLocaleString()}</div>
                </td>
            </tr>
        `;
    });

    html += `</tbody></table>`;
    list.innerHTML = html;
}

function updateSitePrice(id, val) {
    const rate = parseFloat(val) || 0;
    const idx = selectedSites.findIndex(s => s.id == id);
    if (idx !== -1) {
        selectedSites[idx].rate = rate;
        
        // Update main table if row exists
        const mainRow = document.getElementById('row-' + id);
        if (mainRow) {
            const totalCell = mainRow.querySelector('.total-cell');
            if (totalCell) totalCell.innerText = '₹' + rate.toLocaleString();
            // Also update the checkbox's onclick argument if needed, but it's easier to just keep selectedSites as source of truth
        }

        recalcAll();
        updateBucketUI(); 
    }
}

function recalcAll() {
    const subtotalAssets = selectedSites.reduce((acc, s) => acc + s.rate, 0);
    const totalPrinting = selectedSites.reduce((acc, s) => acc + (s.printing_total || 0), 0);
    const subtotal = subtotalAssets + totalPrinting;
    
    const taxType = document.getElementById('tax-type').value;
    const totalTax = subtotal * 0.18;
    const grand = subtotal + totalTax;

    document.getElementById('sum-display-btm').innerText = '₹' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const taxContainer = document.getElementById('tax-breakdown-container');
    if (taxType === 'cgst_sgst') {
        const halfTax = totalTax / 2;
        taxContainer.innerHTML = `
            <div style="display: flex; gap: 1rem;">
                <div style="text-align: right;">
                    <div style="font-size: 0.55rem; color: #64748b; font-weight: 800; text-transform: uppercase;">CGST (9%)</div>
                    <div style="font-weight: 700; color: #1e293b; font-size: 0.8rem;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.55rem; color: #64748b; font-weight: 800; text-transform: uppercase;">SGST (9%)</div>
                    <div style="font-weight: 700; color: #1e293b; font-size: 0.8rem;">₹${halfTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                </div>
            </div>
        `;
    } else {
        taxContainer.innerHTML = `
            <div style="text-align: right;">
                <div style="font-size: 0.6rem; color: #64748b; font-weight: 800; text-transform: uppercase;">IGST (18%)</div>
                <div id="sum-tax-btm" style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">₹${totalTax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
            </div>
        `;
    }

    document.getElementById('sum-grand-btm').innerText = '₹' + grand.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function openBucket() { document.getElementById('selection-bucket-panel').style.right = '0'; document.getElementById('bucket-backdrop').style.display = 'block'; }
function closeBucket() { document.getElementById('selection-bucket-panel').style.right = '-1400px'; document.getElementById('bucket-backdrop').style.display = 'none'; }

function openClientModal() { document.getElementById('clientModal').style.display = 'flex'; }
function closeClientModal() { document.getElementById('clientModal').style.display = 'none'; }

function submitQuickClient() {
    const name = document.getElementById('new_client_name').value;
    const contact = document.getElementById('new_client_contact').value;
    const city = document.getElementById('new_client_city').value;
    if (!name) return Swal.fire('Error', 'Company Name is required', 'error');
    fetch('../../ajax/quick_save_partner.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'client', name, contact_person: contact, city })
    }).then(r => r.json()).then(res => {
        if (res.success) {
            const select = document.getElementById('client_id');
            const opt = new Option(name, res.id);
            opt.dataset.contact = contact;
            opt.selected = true;
            select.add(opt);
            document.getElementById('contact_person').value = contact;
            closeClientModal();
        }
    });
}

function saveDirectBooking() {
    if (selectedSites.length === 0) return Swal.fire('Error', 'Select at least one site', 'error');
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SAVING...';

    const data = {
        client_id: document.getElementById('client_id').value,
        campaign_name: document.getElementById('campaign_name').value,
        contact_person: document.getElementById('contact_person').value,
        start_date: document.getElementById('start_date').value,
        end_date: document.getElementById('end_date').value,
        remark: document.getElementById('remark').value,
        billing_gstin: document.getElementById('selected_gstin')?.value || '',
        tax_type: document.getElementById('tax-type').value,
        site_ids: selectedSites.map(s => s.id),
        rates: selectedSites.reduce((acc, s) => { acc[s.id] = s.rate; return acc; }, {}),
        printing_info: selectedSites.reduce((acc, s) => { 
            acc[s.id] = { 
                vendor_id: s.printing_vendor_id, 
                rate: s.printing_rate, 
                total: s.printing_total 
            }; 
            return acc; 
        }, {})
    };

    fetch('../../ajax/save_direct_po.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(res => {
        if (res.success) {
            Swal.fire('Success', 'Booking  generated!', 'success').then(() => window.location.href = 'bookings.php');
            if(res.po_id) window.open('generate_po.php?po_id=' + res.po_id, '_blank');
        } else {
            Swal.fire('Error', res.message, 'error');
            btn.disabled = false; btn.innerHTML = 'GENERATE BOOKING';
        }
    });
}
// Lightbox & Slider Logic
let currentImages = [];
let currentImgIndex = 0;

function openLightboxSlider(imageString, siteId) {
    if (!imageString) return;
    window.currentLightboxSiteId = siteId;
    currentImages = imageString.split(',');
    currentImgIndex = 0;
    updateSliderImage();
    const lb = document.getElementById('simple-lightbox');
    if(lb) {
        lb.style.display = 'flex';
        const navs = document.querySelectorAll('.slider-nav');
        navs.forEach(n => n.style.display = currentImages.length > 1 ? 'flex' : 'none');
    }
}
function updateSliderImage() {
    const lbImg = document.getElementById('lightbox-img');
    const lbBadge = document.getElementById('lightbox-badge');
    if(lbImg) {
        lbImg.src = imgBaseUrl + currentImages[currentImgIndex];
        if(lbBadge) {
            lbBadge.innerText = (currentImgIndex + 1) + " / " + currentImages.length;
            lbBadge.style.display = currentImages.length > 1 ? 'block' : 'none';
        }

        // Update Button State
        const btn = document.getElementById('primary-btn');
        if(btn && window.currentLightboxSiteId) {
            const idx = selectedSites.findIndex(s => s.id == window.currentLightboxSiteId);
            const isPrimary = (idx !== -1 && selectedSites[idx].thumbnail === currentImages[currentImgIndex]);
            
            if(isPrimary) {
                btn.innerHTML = '<i class="fas fa-check-double"></i> Selected as Primary';
                btn.style.background = '#059669';
            } else {
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Use as Primary Photo';
                btn.style.background = 'var(--primary)';
            }
        }
    }
}

function setPrimaryImage(e) {
    if(e) e.stopPropagation();
    const newThumb = currentImages[currentImgIndex];
    if(window.currentLightboxSiteId) {
        const id = window.currentLightboxSiteId;
        const idx = selectedSites.findIndex(s => s.id == id);
        if(idx !== -1) {
            selectedSites[idx].thumbnail = newThumb;
            
            updateBucketUI();
            
            // Update row image in main table if it exists
            const row = document.getElementById('row-' + id);
            if(row) {
                const img = row.querySelector('img[onclick*="openLightboxSlider"]');
                if(img) {
                    img.src = imgBaseUrl + newThumb;
                    img.style.border = '2px solid #059669';
                }
            }
            
            updateSliderImage(); // Refresh button state

            Swal.fire({
                icon: 'success',
                title: 'Primary Image Set',
                timer: 1000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            Swal.fire('Note', 'Please select this asset first to set its primary image.', 'info');
        }
    }
}

function nextSlide(e) { if(e) e.stopPropagation(); currentImgIndex = (currentImgIndex + 1) % currentImages.length; updateSliderImage(); }
function prevSlide(e) { if(e) e.stopPropagation(); currentImgIndex = (currentImgIndex - 1 + currentImages.length) % currentImages.length; updateSliderImage(); }
function closeLightbox() { const lb = document.getElementById('simple-lightbox'); if(lb) lb.style.display = 'none'; }

document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('simple-lightbox');
    if(lb && lb.style.display === 'flex') {
        if(e.key === 'ArrowRight') nextSlide();
        if(e.key === 'ArrowLeft') prevSlide();
        if(e.key === 'Escape') closeLightbox();
    }
});
</script>

<!-- Simple Lightbox HTML -->
<div id="simple-lightbox" onclick="closeLightbox()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
    <div style="position: relative; max-width: 90%; max-height: 90%; display: flex; align-items: center; justify-content: center;" onclick="event.stopPropagation()">
        <!-- Prev Button -->
        <button class="slider-nav" onclick="prevSlide(event)" style="position: absolute; left: -80px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.5rem; transition: all 0.3s; z-index: 10001;">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div style="position: relative;">
            <img id="lightbox-img" src="" style="max-width: 100%; max-height: 85vh; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.15);">
            
            <!-- Select as Primary Button -->
            <button id="primary-btn" onclick="setPrimaryImage(event)" style="position: absolute; top: 20px; left: 20px; background: var(--primary); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 800; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 10px 20px rgba(0,0,0,0.3); transition: all 0.2s;">
                <i class="fas fa-check-circle"></i> Use as Primary Photo
            </button>

            <!-- Image Counter Badge -->
            <div id="lightbox-badge" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 6px 16px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2);"></div>
        </div>

        <!-- Next Button -->
        <button class="slider-nav" onclick="nextSlide(event)" style="position: absolute; right: -80px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.5rem; transition: all 0.3s; z-index: 10001;">
            <i class="fas fa-chevron-right"></i>
        </button>

        <!-- Close Button -->
        <div onclick="closeLightbox()" style="position: absolute; top: -60px; right: -60px; color: white; font-size: 2.5rem; cursor: pointer; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
