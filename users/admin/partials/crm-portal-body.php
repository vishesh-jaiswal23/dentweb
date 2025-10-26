  <main class="crm-app">
    <section class="section hero crm-hero">
      <div class="container crm-hero__grid">
        <div>
          <p class="badge">Lead to Subsidy Command Centre</p>
          <h1>Manage leads, customers, referrers, and PM Surya Ghar subsidies in one view</h1>
          <p>
            Capture and qualify every enquiry, convert with the required compliance fields, and monitor PM Surya Ghar applications through sanction, inspection, and redemption. Includes referrer visibility, audit logging, duplicate detection, and CSV import/export.
          </p>
          <div class="crm-hero__settings">
            <label class="settings-toggle">
              <span>Workspace role</span>
              <select data-role-select>
                <option value="admin">Admin</option>
                <option value="sales">Sales</option>
                <option value="referrer">Referrer</option>
                <option value="viewer">Viewer</option>
              </select>
            </label>
            <label class="settings-toggle">
              <span>AI assistance</span>
              <input type="checkbox" data-ai-enabled />
            </label>
          </div>
        </div>
        <aside class="crm-hero__metrics">
          <div class="metric-card">
            <p class="metric-label">Open Leads</p>
            <p class="metric-value" data-lead-count>0</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">Active Customers</p>
            <p class="metric-value" data-customer-count>0</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">Avg. Stage Age</p>
            <p class="metric-value" data-stage-age>0 d</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">Sanction Rate</p>
            <p class="metric-value" data-sanction-rate>0%</p>
          </div>
        </aside>
      </div>
    </section>

    <section class="section" id="lead-intake">
      <div class="container">
        <div class="head">
          <h2>Lead capture &amp; qualification</h2>
          <p>Log leads manually, import CSVs, accept referrer submissions, and qualify faster with validation and duplicate detection.</p>
        </div>
        <div class="crm-grid">
          <article class="card" data-permission="sales admin">
            <h3>Manual lead entry</h3>
            <form data-lead-form class="crm-form" novalidate>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">Full name</span>
                  <input type="text" name="name" required />
                </label>
                <label class="form-group">
                  <span class="form-label">Email</span>
                  <input type="email" name="email" required />
                </label>
              </div>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">Phone</span>
                  <input type="tel" name="phone" minlength="10" required />
                </label>
                <label class="form-group">
                  <span class="form-label">City</span>
                  <input type="text" name="city" />
                </label>
              </div>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">System size target (kW)</span>
                  <input type="number" name="systemSize" min="1" step="0.1" />
                </label>
                <label class="form-group">
                  <span class="form-label">Lead source</span>
                  <select name="source">
                    <option value="Manual">Manual</option>
                    <option value="Campaign">Campaign</option>
                    <option value="Walk-in">Walk-in</option>
                  </select>
                </label>
              </div>
              <label class="form-group">
                <span class="form-label">Qualification notes</span>
                <textarea name="notes" rows="2"></textarea>
              </label>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save lead</button>
                <div class="form-alert" data-lead-alert aria-live="polite"></div>
              </div>
            </form>
          </article>

          <article class="card" data-permission="sales admin referrer">
            <h3>Referrer submissions</h3>
            <form data-referrer-form class="crm-form" novalidate>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">Referrer ID</span>
                  <input type="text" name="referrerId" placeholder="REF-001" required />
                </label>
                <label class="form-group">
                  <span class="form-label">Referrer contact</span>
                  <input type="text" name="referrerContact" required />
                </label>
              </div>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">Lead name</span>
                  <input type="text" name="name" required />
                </label>
                <label class="form-group">
                  <span class="form-label">Phone</span>
                  <input type="tel" name="phone" minlength="10" required />
                </label>
              </div>
              <label class="form-group">
                <span class="form-label">Email</span>
                <input type="email" name="email" />
              </label>
              <label class="form-group">
                <span class="form-label">Notes to sales</span>
                <textarea name="notes" rows="2"></textarea>
              </label>
              <div class="form-actions">
                <button type="submit" class="btn btn-secondary">Submit lead</button>
                <div class="form-alert" data-referrer-alert aria-live="polite"></div>
              </div>
            </form>
            <div class="referrer-summary" data-referrer-summary hidden>
              <h4>Your submissions</h4>
              <p><strong data-referrer-lead-count>0</strong> leads • <strong data-referrer-conversion>0%</strong> conversion</p>
              <button type="button" class="btn btn-outline" data-referrer-export>Export CSV</button>
            </div>
          </article>

          <article class="card" data-permission="sales admin">
            <h3>Bulk import &amp; validation</h3>
            <div class="crm-import">
              <label class="form-group">
                <span class="form-label">Upload CSV (name,email,phone,city,systemSize,source,notes)</span>
                <input type="file" accept=".csv" data-lead-import />
              </label>
              <div class="import-results" data-import-results></div>
              <div class="form-actions">
                <button type="button" class="btn btn-outline" data-download-leads>Export leads CSV</button>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section alt" id="lead-pipeline">
      <div class="container">
        <div class="head">
          <h2>Leads &amp; conversion</h2>
          <p>Inspect qualification, add internal notes, and convert to a customer with PM Surya Ghar details in one flow.</p>
        </div>
        <div class="table-wrapper">
          <table class="crm-table" data-lead-table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Source</th>
                <th>Status</th>
                <th>PM Surya Ghar interest</th>
                <th>Notes</th>
                <th>Created</th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="conversion-panel" data-conversion-panel hidden>
          <div class="conversion-header">
            <h3>Convert lead <span data-convert-lead-id></span></h3>
            <button type="button" class="btn btn-icon" data-close-conversion aria-label="Close conversion panel">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <form data-conversion-form class="crm-form" novalidate>
            <input type="hidden" name="leadId" />
            <div class="form-row">
              <label class="form-group">
                <span class="form-label">Customer name</span>
                <input type="text" name="customerName" required />
              </label>
              <label class="form-group">
                <span class="form-label">Primary email</span>
                <input type="email" name="customerEmail" required />
              </label>
            </div>
            <div class="form-row">
              <label class="form-group">
                <span class="form-label">Primary phone</span>
                <input type="tel" name="customerPhone" minlength="10" required />
              </label>
              <label class="form-group">
                <span class="form-label">System size (kW)</span>
                <input type="number" name="systemSize" min="1" step="0.1" required />
              </label>
            </div>
            <div class="form-row">
              <label class="form-group">
                <span class="form-label">Subsidy status</span>
                <select name="subsidyStatus" required>
                  <option value="Not Applied">Not Applied</option>
                  <option value="Applied">Applied</option>
                  <option value="Sanctioned">Sanctioned</option>
                  <option value="Redeemed">Redeemed</option>
                </select>
              </label>
              <label class="form-group">
                <span class="form-label">PM Surya Ghar application #</span>
                <input type="text" name="applicationNumber" placeholder="Required if subsidy applied" />
              </label>
            </div>
            <div class="form-row">
              <label class="form-group">
                <span class="form-label">Billing start date</span>
                <input type="date" name="billingStart" />
              </label>
              <label class="form-group">
                <span class="form-label">Billing end date</span>
                <input type="date" name="billingEnd" />
              </label>
            </div>
            <fieldset class="form-group">
              <legend class="form-label">PM Surya Ghar specifics</legend>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">DISCOM</span>
                  <input type="text" name="discom" />
                </label>
                <label class="form-group">
                  <span class="form-label">Feasibility approval date</span>
                  <input type="date" name="feasibilityDate" />
                </label>
              </div>
              <label class="form-group">
                <span class="form-label">Documents received</span>
                <textarea name="documents" rows="2" placeholder="Aadhaar, Electricity bill, Site photos"></textarea>
              </label>
            </fieldset>
            <fieldset class="form-group">
              <legend class="form-label">Contact directory</legend>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">Accounts contact</span>
                  <input type="text" name="accountsContact" />
                </label>
                <label class="form-group">
                  <span class="form-label">Technical contact</span>
                  <input type="text" name="technicalContact" />
                </label>
              </div>
            </fieldset>
            <fieldset class="form-group">
              <legend class="form-label">Schedule installer visit (optional)</legend>
              <div class="form-row">
                <label class="form-group">
                  <span class="form-label">Visit date</span>
                  <input type="datetime-local" name="visitDate" />
                </label>
                <label class="form-group">
                  <span class="form-label">Installer notes</span>
                  <input type="text" name="visitNotes" />
                </label>
              </div>
            </fieldset>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Convert lead</button>
              <div class="form-alert" data-conversion-alert aria-live="polite"></div>
            </div>
          </form>
        </div>
      </div>
    </section>

    <section class="section" id="customer-crm">
      <div class="container">
        <div class="head">
          <h2>Customer CRM essentials</h2>
          <p>Search and filter customer records, update subsidy stages, and export data for MIS reporting.</p>
        </div>
        <div class="filters" data-permission="sales admin">
          <div class="form-row">
            <label class="form-group">
              <span class="form-label">Search</span>
              <input type="search" data-customer-search placeholder="Name, phone, application #" />
            </label>
            <label class="form-group">
              <span class="form-label">Subsidy status</span>
              <select data-filter-subsidy>
                <option value="">All</option>
                <option value="Not Applied">Not Applied</option>
                <option value="Applied">Applied</option>
                <option value="Sanctioned">Sanctioned</option>
                <option value="Inspected">Inspected</option>
                <option value="Redeemed">Redeemed</option>
                <option value="Closed">Closed</option>
              </select>
            </label>
            <label class="form-group">
              <span class="form-label">PM Surya Ghar stage</span>
              <select data-filter-stage>
                <option value="">All</option>
                <option value="Applied">Applied</option>
                <option value="Sanctioned">Sanctioned</option>
                <option value="Inspected">Inspected</option>
                <option value="Redeemed">Redeemed</option>
                <option value="Closed">Closed</option>
              </select>
            </label>
            <label class="form-group">
              <span class="form-label">Created from</span>
              <input type="date" data-filter-from />
            </label>
            <label class="form-group">
              <span class="form-label">Created to</span>
              <input type="date" data-filter-to />
            </label>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-outline" data-export-customers>Export customers CSV</button>
            <label class="btn btn-secondary file-input">
              Import customers CSV
              <input type="file" accept=".csv" data-import-customers hidden />
            </label>
            <div class="form-alert" data-customer-import-alert aria-live="polite"></div>
          </div>
        </div>
        <div class="crm-two-column">
          <div class="table-wrapper">
            <table class="crm-table" data-customer-table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Application #</th>
                  <th>Stage</th>
                  <th>Subsidy status</th>
                  <th>System size</th>
                  <th>Billing start</th>
                  <th></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <aside class="card" data-stage-manager>
            <h3>PM Surya Ghar stage flow</h3>
            <form data-stage-form class="crm-form" novalidate>
              <input type="hidden" name="customerId" />
              <p class="form-help">Update stages sequentially. Required documents and milestone dates must be captured before advancing.</p>
              <label class="form-group">
                <span class="form-label">Next stage</span>
                <select name="stage" required>
                  <option value="Applied">Applied</option>
                  <option value="Sanctioned">Sanctioned</option>
                  <option value="Inspected">Inspected</option>
                  <option value="Redeemed">Redeemed</option>
                  <option value="Closed">Closed</option>
                </select>
              </label>
              <label class="form-group">
                <span class="form-label">Stage date</span>
                <input type="date" name="stageDate" required />
              </label>
              <label class="form-group">
                <span class="form-label">Documents submitted</span>
                <textarea name="stageDocuments" rows="2" placeholder="Work order, Inspection photos, Net meter report" required></textarea>
              </label>
              <label class="form-group">
                <span class="form-label">Inspector / verifier</span>
                <input type="text" name="stageOwner" required />
              </label>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Advance stage</button>
                <div class="form-alert" data-stage-alert aria-live="polite"></div>
              </div>
            </form>
            <div class="stage-metrics">
              <p><strong>Current stage:</strong> <span data-current-stage>–</span></p>
              <p><strong>Days in stage:</strong> <span data-current-stage-age>0</span></p>
              <button type="button" class="btn btn-outline" data-export-stage-history>Export stage history</button>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <section class="section alt" id="pipeline-analytics">
      <div class="container">
        <div class="head">
          <h2>Pipeline analytics &amp; health</h2>
          <p>Monitor stage load, average time-in-stage, and aging to prioritise interventions.</p>
        </div>
        <div class="pipeline-grid">
          <article class="card">
            <h3>Stage load</h3>
            <ul class="stage-list" data-stage-counts></ul>
          </article>
          <article class="card">
            <h3>Average days in stage</h3>
            <ul class="stage-list" data-stage-averages></ul>
          </article>
          <article class="card">
            <h3>Aging watchlist</h3>
            <ul class="stage-list" data-stage-aging></ul>
          </article>
          <article class="card">
            <h3>Alerts &amp; anomalies</h3>
            <ul class="stage-list" data-anomalies></ul>
          </article>
        </div>
      </div>
    </section>

    <section class="section" id="audit">
      <div class="container">
        <div class="head">
          <h2>Audit log &amp; security</h2>
          <p>Every mutation is logged with timestamp, actor, and affected records. Actions without permission are denied.</p>
        </div>
        <div class="crm-grid">
          <article class="card">
            <h3>Recent activity</h3>
            <ul class="activity-log" data-activity-log></ul>
          </article>
          <article class="card">
            <h3>Permission events</h3>
            <ul class="activity-log" data-permission-log></ul>
          </article>
        </div>
      </div>
    </section>
  </main>
