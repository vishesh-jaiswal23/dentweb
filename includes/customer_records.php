<?php
declare(strict_types=1);

final class CustomerRecordStore
{
    private const DATA_VERSION = 1;

    public const TYPE_LEAD = 'lead';
    public const TYPE_CUSTOMER = 'customer';

    private const LEAD_COLUMNS = [
        'Full Name',
        'Mobile Number',
        'Address',
        'District',
        'Pin Code',
        'Lead Source',
        'Lead Status',
        'Internal Remarks',
    ];

    private const CUSTOMER_COLUMNS = [
        'Full Name',
        'Mobile Number',
        'Address',
        'District',
        'Pin Code',
        'Aadhaar Number',
        'PAN Number',
        'DISCOM Name',
        'Consumer Name',
        'Subdivision Name',
        'New Meter Required',
        'Load Type',
        'Phase',
        'Sanctioned Load (kW)',
        'Lead Source',
        'Lead Status',
        'Quotation Date',
        'Quotation Number',
        'System Type (kW)',
        'System Category',
        'Installation Status',
        'Project Cost',
        'PM-Surya Ghar',
        'PM-SGY Application ID',
        'Actual Bill Date',
        'GST Bill Date',
        'Handover Date',
        'Panel Warranty',
        'Inverter Warranty',
        'Structure Warranty',
        'Service Warranty',
        'Assigned Employee',
        'Internal Remarks',
        'Complaint Status',
    ];

    private string $basePath;
    private string $dataPath;
    private string $lockPath;
    private string $credentialsPath;

    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? (__DIR__ . '/../storage/customer-records');
        $this->dataPath = $this->basePath . '/records.json';
        $this->lockPath = $this->basePath . '/records.lock';
        $this->credentialsPath = $this->basePath . '/portal-credentials.log';

        $this->initialiseFilesystem();
    }

    public function __destruct()
    {
        $this->releaseLock();
    }

    public function leads(): array
    {
        return $this->listByType(self::TYPE_LEAD);
    }

    public function customers(): array
    {
        return $this->listByType(self::TYPE_CUSTOMER);
    }

    public function all(): array
    {
        $data = $this->readData();
        return $this->prepareCollection($data['records']);
    }

    public function find(int $id): ?array
    {
        $data = $this->readData();
        $record = $data['records'][(string) $id] ?? null;

        return is_array($record) ? $this->prepareRecordForOutput($record) : null;
    }

    public function findByMobile(string $mobile): ?array
    {
        $normalized = $this->normalizeMobile($mobile);
        if ($normalized === '') {
            return null;
        }

        $data = $this->readData();
        $id = $data['mobile_index'][$normalized] ?? null;
        if ($id === null) {
            return null;
        }

        $record = $data['records'][(string) $id] ?? null;

        return is_array($record) ? $this->prepareRecordForOutput($record) : null;
    }

    public function importCsv(string $type, string $csvContents): array
    {
        $type = strtolower(trim($type));
        $mode = $type === 'customers' ? 'customers' : 'leads';

        $rows = $this->parseCsv($mode, $csvContents);

        return $this->withLock(function () use ($rows): array {
            $data = $this->readData();
            $summary = [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'customers' => 0,
                'leads' => 0,
            ];

            $now = $this->now();
            foreach ($rows as $row) {
                $summary['processed']++;
                $record = $this->mapRowToRecord($row);
                $record['updated_at'] = $now;
                if (!isset($record['created_at']) || $record['created_at'] === null) {
                    $record['created_at'] = $now;
                }

                $existingId = null;
                if ($record['mobile_normalized'] !== '') {
                    $existingId = $data['mobile_index'][$record['mobile_normalized']] ?? null;
                }

                $existing = null;
                $wasCustomer = false;
                if ($existingId !== null) {
                    $record['id'] = (int) $existingId;
                    $existing = $data['records'][(string) $existingId] ?? [];
                    if (isset($existing['created_at'])) {
                        $record['created_at'] = $existing['created_at'];
                    }
                    $previousMobile = is_array($existing) ? ($existing['mobile_normalized'] ?? '') : '';
                    if ($previousMobile !== '' && $previousMobile !== $record['mobile_normalized']) {
                        unset($data['mobile_index'][$previousMobile]);
                    }
                    $summary['updated']++;
                    $wasCustomer = strtolower((string) ($existing['record_type'] ?? '')) === self::TYPE_CUSTOMER;
                } else {
                    $data['last_id']++;
                    $record['id'] = $data['last_id'];
                    $summary['created']++;
                }

                $record['record_type'] = $this->determineType($record);
                if ($record['record_type'] === self::TYPE_CUSTOMER) {
                    $summary['customers']++;
                } else {
                    $summary['leads']++;
                }

                $record = $this->ensureCustomerPortalAccount($record, $wasCustomer);

                $data['records'][(string) $record['id']] = $record;
                if ($record['mobile_normalized'] !== '') {
                    $data['mobile_index'][$record['mobile_normalized']] = $record['id'];
                }
            }

            $this->writeData($data);

            return $summary;
        });
    }

    public function updateInstallationStatus(int $id, string $installationStatus): array
    {
        return $this->withLock(function () use ($id, $installationStatus): array {
            $data = $this->readData();
            $record = $data['records'][(string) $id] ?? null;
            if (!is_array($record)) {
                throw new RuntimeException('Record not found.');
            }

            $record['installation_status'] = $this->normalizeInstallationStatus($installationStatus);
            if ($record['installation_status'] === 'commissioned' && $this->normalizeLeadStatus($record['lead_status'] ?? '') !== 'converted') {
                $record['lead_status'] = 'converted';
            }
            $record['record_type'] = $this->determineType($record);
            $record['updated_at'] = $this->now();

            $data['records'][(string) $id] = $record;
            $this->writeData($data);

            return $this->prepareRecordForOutput($record);
        });
    }

    public function templateColumns(string $type): array
    {
        $type = strtolower(trim($type));
        if ($type === 'customers') {
            return self::CUSTOMER_COLUMNS;
        }
        return self::LEAD_COLUMNS;
    }

    private function listByType(string $type): array
    {
        $type = strtolower($type);
        $data = $this->readData();
        $filtered = array_filter(
            $data['records'],
            static fn ($record): bool => is_array($record) && strtolower((string) ($record['record_type'] ?? '')) === $type
        );

        return $this->prepareCollection($filtered);
    }

    private function prepareCollection(array $records): array
    {
        $prepared = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $prepared[] = $this->prepareRecordForOutput($record);
        }

        usort($prepared, static function (array $left, array $right): int {
            return strcmp($left['full_name'] ?? '', $right['full_name'] ?? '');
        });

        return $prepared;
    }

    private function prepareRecordForOutput(array $record): array
    {
        $record['record_type'] = $this->determineType($record);
        return $record;
    }

    private function mapRowToRecord(array $row): array
    {
        $record = $row;
        $record['full_name'] = $this->sanitizeName($record['full_name'] ?? '');
        $record['mobile_number'] = $this->sanitizeMobileForStorage($record['mobile_number'] ?? '');
        $record['mobile_normalized'] = $this->normalizeMobile($record['mobile_number']);
        $record['address'] = $this->sanitizeString($record['address'] ?? '');
        $record['district'] = $this->sanitizeString($record['district'] ?? '');
        $record['pin_code'] = $this->sanitizeString($record['pin_code'] ?? '');
        $record['lead_source'] = $this->sanitizeString($record['lead_source'] ?? '');
        $record['lead_status'] = $this->normalizeLeadStatus($record['lead_status'] ?? '');
        $record['installation_status'] = $this->normalizeInstallationStatus($record['installation_status'] ?? '');
        $record['internal_remarks'] = $this->sanitizeString($record['internal_remarks'] ?? '');

        foreach ([
            'aadhaar_number',
            'pan_number',
            'discom_name',
            'consumer_name',
            'subdivision_name',
            'new_meter_required',
            'load_type',
            'phase',
            'sanctioned_load_kw',
            'quotation_date',
            'quotation_number',
            'system_type_kw',
            'system_category',
            'project_cost',
            'pm_surya_ghar',
            'pm_sgy_application_id',
            'actual_bill_date',
            'gst_bill_date',
            'handover_date',
            'panel_warranty',
            'inverter_warranty',
            'structure_warranty',
            'service_warranty',
            'assigned_employee',
            'complaint_status',
        ] as $field) {
            if (!isset($record[$field])) {
                $record[$field] = '';
            } else {
                $record[$field] = $this->sanitizeString($record[$field]);
            }
        }

        return $record;
    }

    private function parseCsv(string $mode, string $contents): array
    {
        $columns = $mode === 'customers' ? self::CUSTOMER_COLUMNS : self::LEAD_COLUMNS;
        $map = $this->columnMapping($mode);

        $handle = fopen('php://temp', 'wb+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary memory stream for CSV.');
        }

        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                if (isset($row[0])) {
                    $row[0] = $this->stripBom((string) $row[0]);
                }
                $header = $row;
                continue;
            }

            $isEmpty = true;
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        if ($header === null) {
            throw new RuntimeException('The uploaded file did not contain a header row.');
        }

        if (count($header) !== count($columns)) {
            throw new RuntimeException('The uploaded CSV does not match the required column count.');
        }

        foreach ($columns as $index => $columnName) {
            $received = isset($header[$index]) ? trim((string) $header[$index]) : '';
            if (strcasecmp($received, $columnName) !== 0) {
                throw new RuntimeException('The uploaded CSV header does not match the required template.');
            }
        }

        $records = [];
        foreach ($rows as $row) {
            $record = [];
            foreach ($columns as $index => $columnName) {
                $key = $map[$columnName];
                $record[$key] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }
            $records[] = $record;
        }

        return $records;
    }

    private function columnMapping(string $mode): array
    {
        if ($mode === 'customers') {
            return [
                'Full Name' => 'full_name',
                'Mobile Number' => 'mobile_number',
                'Address' => 'address',
                'District' => 'district',
                'Pin Code' => 'pin_code',
                'Aadhaar Number' => 'aadhaar_number',
                'PAN Number' => 'pan_number',
                'DISCOM Name' => 'discom_name',
                'Consumer Name' => 'consumer_name',
                'Subdivision Name' => 'subdivision_name',
                'New Meter Required' => 'new_meter_required',
                'Load Type' => 'load_type',
                'Phase' => 'phase',
                'Sanctioned Load (kW)' => 'sanctioned_load_kw',
                'Lead Source' => 'lead_source',
                'Lead Status' => 'lead_status',
                'Quotation Date' => 'quotation_date',
                'Quotation Number' => 'quotation_number',
                'System Type (kW)' => 'system_type_kw',
                'System Category' => 'system_category',
                'Installation Status' => 'installation_status',
                'Project Cost' => 'project_cost',
                'PM-Surya Ghar' => 'pm_surya_ghar',
                'PM-SGY Application ID' => 'pm_sgy_application_id',
                'Actual Bill Date' => 'actual_bill_date',
                'GST Bill Date' => 'gst_bill_date',
                'Handover Date' => 'handover_date',
                'Panel Warranty' => 'panel_warranty',
                'Inverter Warranty' => 'inverter_warranty',
                'Structure Warranty' => 'structure_warranty',
                'Service Warranty' => 'service_warranty',
                'Assigned Employee' => 'assigned_employee',
                'Internal Remarks' => 'internal_remarks',
                'Complaint Status' => 'complaint_status',
            ];
        }

        return [
            'Full Name' => 'full_name',
            'Mobile Number' => 'mobile_number',
            'Address' => 'address',
            'District' => 'district',
            'Pin Code' => 'pin_code',
            'Lead Source' => 'lead_source',
            'Lead Status' => 'lead_status',
            'Internal Remarks' => 'internal_remarks',
        ];
    }

    private function determineType(array $record): string
    {
        $installationStatus = $this->normalizeInstallationStatus($record['installation_status'] ?? '');
        $leadStatus = $this->normalizeLeadStatus($record['lead_status'] ?? '');

        if ($installationStatus === 'commissioned' && $leadStatus === 'converted') {
            return self::TYPE_CUSTOMER;
        }

        return self::TYPE_LEAD;
    }

    private function sanitizeName(string $value): string
    {
        $value = trim($value);
        return $value === '' ? 'Unknown' : $value;
    }

    private function sanitizeString(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        return trim($value);
    }

    private function sanitizeMobileForStorage(?string $value): string
    {
        $normalized = $this->normalizeMobile($value ?? '');
        if ($normalized === '') {
            return '';
        }
        return $normalized;
    }

    private function normalizeMobile(string $value): string
    {
        if (function_exists('normalize_customer_mobile')) {
            $digits = normalize_customer_mobile($value);
        } else {
            $digits = preg_replace('/\D+/', '', $value);
            if (!is_string($digits)) {
                $digits = '';
            }
            $digits = trim($digits);
        }

        if ($digits !== '' && strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    private function normalizeLeadStatus(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $normalized = strtolower(trim($value));
        return $normalized;
    }

    private function normalizeInstallationStatus(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        return strtolower(trim($value));
    }

    private function stripBom(string $value): string
    {
        if (substr($value, 0, 3) === "\xEF\xBB\xBF") {
            return substr($value, 3);
        }
        return $value;
    }

    private function initialiseFilesystem(): void
    {
        if (!is_dir($this->basePath) && !@mkdir($this->basePath, 0775, true) && !is_dir($this->basePath)) {
            throw new RuntimeException('Unable to initialise customer record storage.');
        }

        if (!is_file($this->dataPath)) {
            $this->writeData($this->defaultData());
        }

        if (!is_file($this->lockPath)) {
            $handle = fopen($this->lockPath, 'c');
            if ($handle === false) {
                throw new RuntimeException('Unable to prepare customer record lock file.');
            }
            fclose($handle);
        }

        if (!is_file($this->credentialsPath)) {
            touch($this->credentialsPath);
        }
    }

    private function ensureCustomerPortalAccount(array $record, bool $alreadyCustomer): array
    {
        if (strtolower((string) ($record['record_type'] ?? '')) !== self::TYPE_CUSTOMER) {
            return $record;
        }

        if ($alreadyCustomer) {
            return $record;
        }

        if (!function_exists('user_store')) {
            return $record;
        }

        $mobile = (string) ($record['mobile_normalized'] ?? '');
        if ($mobile === '') {
            return $record;
        }

        try {
            $store = user_store();
        } catch (Throwable $exception) {
            error_log('CustomerRecordStore: unable to open user store: ' . $exception->getMessage());
            return $record;
        }

        try {
            if (method_exists($store, 'findByLoginIdentifier')) {
                $existing = $store->findByLoginIdentifier($mobile, 'customer');
                if (is_array($existing)) {
                    return $record;
                }
            }
        } catch (Throwable $exception) {
            error_log('CustomerRecordStore: unable to check existing customer login: ' . $exception->getMessage());
        }

        $fullName = trim((string) ($record['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Customer ' . substr($mobile, -4);
        }

        $nameSlug = strtolower(preg_replace('/[^a-z]/', '', $fullName));
        if ($nameSlug === '') {
            $nameSlug = 'customer';
        }
        $base = substr($nameSlug, 0, 6);
        if ($base === '') {
            $base = 'cust';
        }
        $mobileSuffix = substr($mobile, -4);
        if ($mobileSuffix === '') {
            $mobileSuffix = substr(str_pad('', 4, '7'), 0, 4);
        }

        $candidate = $base . $mobileSuffix;
        $suffix = 1;
        try {
            while ($store->findByIdentifier($candidate) !== null) {
                $candidate = $base . $mobileSuffix . $suffix;
                $suffix++;
            }
        } catch (Throwable $exception) {
            error_log('CustomerRecordStore: unable to ensure unique username: ' . $exception->getMessage());
        }
        $username = $candidate;

        $passwordSeed = substr($nameSlug, 0, 3);
        if (strlen($passwordSeed) < 3) {
            $passwordSeed = str_pad($passwordSeed, 3, 'x');
        }
        $password = ucfirst($passwordSeed) . '@' . $mobileSuffix;
        if (strlen($password) < 8) {
            $password .= substr(strrev($mobile), 0, 8 - strlen($password));
        }

        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $store->save([
                'full_name' => $fullName,
                'username' => $username,
                'phone' => $mobile,
                'role' => 'customer',
                'status' => 'active',
                'password_hash' => $passwordHash,
                'permissions_note' => 'Auto-created from customer records import',
            ]);
        } catch (Throwable $exception) {
            error_log('CustomerRecordStore: failed to provision customer login: ' . $exception->getMessage());
            return $record;
        }

        $record['portal_username'] = $username;
        $record['portal_account_created_at'] = $this->now();
        $this->logCustomerCredential($record, $username, $password);

        return $record;
    }

    private function logCustomerCredential(array $record, string $username, string $password): void
    {
        $timestamp = $this->now();
        $id = isset($record['id']) ? (int) $record['id'] : 0;
        $line = sprintf(
            "%s\tID:%s\t%s\t%s\t%s\n",
            $timestamp,
            $id > 0 ? (string) $id : '-',
            (string) ($record['full_name'] ?? ''),
            $username,
            $password
        );

        file_put_contents($this->credentialsPath, $line, FILE_APPEND | LOCK_EX);
    }

    private function defaultData(): array
    {
        return [
            'version' => self::DATA_VERSION,
            'last_id' => 0,
            'records' => [],
            'mobile_index' => [],
        ];
    }

    private function readData(): array
    {
        $contents = @file_get_contents($this->dataPath);
        if ($contents === false || $contents === '') {
            return $this->defaultData();
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            error_log('CustomerRecordStore: invalid JSON encountered, resetting storage. ' . $exception->getMessage());
            return $this->defaultData();
        }

        if (!is_array($decoded)) {
            return $this->defaultData();
        }

        foreach (['version', 'last_id', 'records', 'mobile_index'] as $key) {
            if (!array_key_exists($key, $decoded)) {
                $decoded[$key] = $this->defaultData()[$key];
            }
        }

        return $decoded;
    }

    private function writeData(array $data): void
    {
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new RuntimeException('Failed to encode customer records for storage.');
        }

        $temp = tempnam($this->basePath, 'records');
        if ($temp === false) {
            throw new RuntimeException('Unable to create temporary file for customer records.');
        }

        $bytes = file_put_contents($temp, $payload, LOCK_EX);
        if ($bytes === false) {
            @unlink($temp);
            throw new RuntimeException('Failed to write customer record data.');
        }

        if (!@rename($temp, $this->dataPath)) {
            @unlink($temp);
            throw new RuntimeException('Failed to commit customer records update.');
        }
    }

    private function now(): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
        return $now->format('Y-m-d H:i:s');
    }

    private function withLock(callable $callback)
    {
        $this->acquireLock();

        try {
            return $callback();
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): void
    {
        if ($this->lockHandle !== null) {
            return;
        }

        $handle = fopen($this->lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open customer record lock.');
        }

        if (flock($handle, LOCK_EX) !== true) {
            fclose($handle);
            throw new RuntimeException('Unable to acquire customer record lock.');
        }

        $this->lockHandle = $handle;
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle === null) {
            return;
        }

        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
        $this->lockHandle = null;
    }
}

function customer_record_store(?string $basePath = null): CustomerRecordStore
{
    static $store = null;

    if ($basePath !== null) {
        return new CustomerRecordStore($basePath);
    }

    if (!$store instanceof CustomerRecordStore) {
        $store = new CustomerRecordStore();
    }

    return $store;
}

function customer_records_leads(): array
{
    return customer_record_store()->leads();
}

function customer_records_customers(): array
{
    return customer_record_store()->customers();
}

function customer_records_can_login(string $identifier): bool
{
    $store = customer_record_store();
    $record = $store->findByMobile($identifier);
    if ($record === null) {
        return false;
    }

    return strtolower((string) ($record['record_type'] ?? '')) === CustomerRecordStore::TYPE_CUSTOMER;
}

function customer_records_template_columns(string $type): array
{
    return customer_record_store()->templateColumns($type);
}
