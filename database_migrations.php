<?php
declare(strict_types=1);

/**
 * Additive-only database migration engine.
 * Every generated operation is CREATE, ALTER ... ADD, or INSERT IGNORE.
 */

function migrationTableExists(string $table): bool {
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function migrationColumnExists(string $table, string $column): bool {
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function migrationIndexExists(string $table, string $index): bool {
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function fullMigrationTableDefinitions(): array {
    return [
        'questions' => "CREATE TABLE IF NOT EXISTS questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question_number TINYINT UNSIGNED NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'participants' => "CREATE TABLE IF NOT EXISTS participants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            name_normalized VARCHAR(100) NOT NULL,
            whatsapp VARCHAR(20) NOT NULL,
            tiktok_account VARCHAR(100) NOT NULL,
            tiktok_profile_url VARCHAR(500) NOT NULL,
            subscriber_photo VARCHAR(255) NOT NULL,
            comment_photo VARCHAR(255) NOT NULL,
            token VARCHAR(32) NOT NULL,
            submit_ip VARCHAR(45) NULL,
            device_hash CHAR(64) NULL,
            subscriber_image_hash CHAR(64) NULL,
            comment_image_hash CHAR(64) NULL,
            risk_status ENUM('clear','flagged') NOT NULL DEFAULT 'clear',
            risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            risk_reasons VARCHAR(1000) NULL,
            privacy_consent_at DATETIME NULL,
            privacy_policy_version VARCHAR(20) NULL,
            age_confirmed_at DATETIME NULL,
            status ENUM('pending','reviewed') NOT NULL DEFAULT 'pending',
            correction_message TEXT NULL,
            correct_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            submitted_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            UNIQUE KEY uq_name_normalized(name_normalized),
            UNIQUE KEY uq_whatsapp(whatsapp),
            UNIQUE KEY uq_tiktok_account(tiktok_account),
            UNIQUE KEY uq_device_hash(device_hash),
            UNIQUE KEY uq_token(token),
            KEY idx_status(status),
            KEY idx_subscriber_hash(subscriber_image_hash),
            KEY idx_comment_hash(comment_image_hash),
            KEY idx_risk_status(risk_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'participant_answers' => "CREATE TABLE IF NOT EXISTS participant_answers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            participant_id BIGINT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            answer_text TEXT NOT NULL,
            is_correct TINYINT(1) NULL,
            correction_note VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_participant_question(participant_id,question_id),
            CONSTRAINT fk_answer_participant FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE,
            CONSTRAINT fk_answer_question FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'participant_identity_locks' => "CREATE TABLE IF NOT EXISTS participant_identity_locks (
            identity_type ENUM('whatsapp','tiktok','device') NOT NULL,
            identity_value VARCHAR(191) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(identity_type,identity_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'participant_answer_images' => "CREATE TABLE IF NOT EXISTS participant_answer_images (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            participant_answer_id BIGINT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            image_hash CHAR(64) NULL,
            sort_order TINYINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_answer_image_order(participant_answer_id,sort_order),
            KEY idx_answer_image_answer(participant_answer_id),
            KEY idx_answer_image_hash(image_hash),
            CONSTRAINT fk_answer_image_answer FOREIGN KEY(participant_answer_id) REFERENCES participant_answers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'raffle_sequence' => "CREATE TABLE IF NOT EXISTS raffle_sequence (
            id TINYINT UNSIGNED PRIMARY KEY,
            next_number BIGINT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'raffle_numbers' => "CREATE TABLE IF NOT EXISTS raffle_numbers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            participant_id BIGINT UNSIGNED NOT NULL,
            raffle_number VARCHAR(40) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_raffle_participant(participant_id),
            CONSTRAINT fk_raffle_participant FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'admins' => "CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'submission_attempts' => "CREATE TABLE IF NOT EXISTS submission_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            whatsapp VARCHAR(20) NULL,
            tiktok_account VARCHAR(100) NULL,
            device_hash CHAR(64) NULL,
            was_successful TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_submit_ip_time(ip_address,attempted_at),
            KEY idx_submit_device_time(device_hash,attempted_at),
            KEY idx_submit_tiktok_time(tiktok_account,attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'admin_login_attempts' => "CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            was_successful TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_admin_attempt_ip_time(ip_address,attempted_at),
            KEY idx_admin_attempt_user_time(username,attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'app_settings' => "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'database_migrations' => "CREATE TABLE IF NOT EXISTS database_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration_key VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

function fullMigrationColumnDefinitions(): array {
    return [
        'questions' => ['id'=>'INT UNSIGNED NULL','question_number'=>'TINYINT UNSIGNED NULL','is_active'=>"TINYINT(1) NOT NULL DEFAULT 1",'created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        'participants' => [
            'id'=>'BIGINT UNSIGNED NULL','name'=>'VARCHAR(100) NULL','name_normalized'=>'VARCHAR(100) NULL','whatsapp'=>'VARCHAR(20) NULL',
            'tiktok_account'=>'VARCHAR(100) NULL','tiktok_profile_url'=>'VARCHAR(500) NULL','subscriber_photo'=>'VARCHAR(255) NULL',
            'comment_photo'=>'VARCHAR(255) NULL','token'=>'VARCHAR(32) NULL','submit_ip'=>'VARCHAR(45) NULL','device_hash'=>'CHAR(64) NULL',
            'subscriber_image_hash'=>'CHAR(64) NULL','comment_image_hash'=>'CHAR(64) NULL',
            'risk_status'=>"ENUM('clear','flagged') NOT NULL DEFAULT 'clear'",'risk_score'=>'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'risk_reasons'=>'VARCHAR(1000) NULL','privacy_consent_at'=>'DATETIME NULL','privacy_policy_version'=>'VARCHAR(20) NULL',
            'age_confirmed_at'=>'DATETIME NULL','status'=>"ENUM('pending','reviewed') NOT NULL DEFAULT 'pending'",
            'correction_message'=>'TEXT NULL','correct_count'=>'TINYINT UNSIGNED NOT NULL DEFAULT 0','submitted_at'=>'DATETIME NULL','reviewed_at'=>'DATETIME NULL',
        ],
        'participant_answers' => ['id'=>'BIGINT UNSIGNED NULL','participant_id'=>'BIGINT UNSIGNED NULL','question_id'=>'INT UNSIGNED NULL','answer_text'=>'TEXT NULL','is_correct'=>'TINYINT(1) NULL','correction_note'=>'VARCHAR(500) NULL','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        'participant_identity_locks' => ['identity_type'=>"ENUM('whatsapp','tiktok','device') NULL",'identity_value'=>'VARCHAR(191) NULL','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        'participant_answer_images' => ['id'=>'BIGINT UNSIGNED NULL','participant_answer_id'=>'BIGINT UNSIGNED NULL','image_path'=>'VARCHAR(255) NULL','image_hash'=>'CHAR(64) NULL','sort_order'=>'TINYINT UNSIGNED NULL','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        'raffle_sequence' => ['id'=>'TINYINT UNSIGNED NULL','next_number'=>'BIGINT UNSIGNED NOT NULL DEFAULT 1'],
        'raffle_numbers' => ['id'=>'BIGINT UNSIGNED NULL','participant_id'=>'BIGINT UNSIGNED NULL','raffle_number'=>'VARCHAR(40) NULL','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        'admins' => ['id'=>'INT UNSIGNED NULL','username'=>'VARCHAR(50) NULL','password_hash'=>'VARCHAR(255) NULL','must_change_password'=>'TINYINT(1) NOT NULL DEFAULT 0','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP','updated_at'=>'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        'submission_attempts' => ['id'=>'BIGINT UNSIGNED NULL','ip_address'=>'VARCHAR(45) NULL','whatsapp'=>'VARCHAR(20) NULL','tiktok_account'=>'VARCHAR(100) NULL','device_hash'=>'CHAR(64) NULL','was_successful'=>'TINYINT(1) NOT NULL DEFAULT 0','attempted_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        'admin_login_attempts' => ['id'=>'BIGINT UNSIGNED NULL','username'=>'VARCHAR(50) NULL','ip_address'=>'VARCHAR(45) NULL','was_successful'=>'TINYINT(1) NOT NULL DEFAULT 0','attempted_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        'app_settings' => ['setting_key'=>'VARCHAR(50) NULL','setting_value'=>'VARCHAR(255) NULL','updated_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        'database_migrations' => ['id'=>'BIGINT UNSIGNED NULL','migration_key'=>'VARCHAR(100) NULL','description'=>'VARCHAR(255) NULL','applied_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
    ];
}

function fullMigrationIndexDefinitions(): array {
    return [
        'participants' => [
            'idx_status'=>'KEY idx_status(status)','idx_subscriber_hash'=>'KEY idx_subscriber_hash(subscriber_image_hash)',
            'idx_comment_hash'=>'KEY idx_comment_hash(comment_image_hash)','idx_risk_status'=>'KEY idx_risk_status(risk_status)',
        ],
        'participant_answer_images' => ['idx_answer_image_answer'=>'KEY idx_answer_image_answer(participant_answer_id)','idx_answer_image_hash'=>'KEY idx_answer_image_hash(image_hash)'],
        'raffle_numbers' => ['idx_raffle_participant'=>'KEY idx_raffle_participant(participant_id)'],
        'submission_attempts' => ['idx_submit_ip_time'=>'KEY idx_submit_ip_time(ip_address,attempted_at)','idx_submit_device_time'=>'KEY idx_submit_device_time(device_hash,attempted_at)','idx_submit_tiktok_time'=>'KEY idx_submit_tiktok_time(tiktok_account,attempted_at)'],
        'admin_login_attempts' => ['idx_admin_attempt_ip_time'=>'KEY idx_admin_attempt_ip_time(ip_address,attempted_at)','idx_admin_attempt_user_time'=>'KEY idx_admin_attempt_user_time(username,attempted_at)'],
    ];
}

function databaseMigrationPlan(): array {
    $plan = [];
    foreach (fullMigrationTableDefinitions() as $table => $sql) {
        if (!migrationTableExists($table)) $plan[] = ['type'=>'table','label'=>'Buat tabel '.$table,'sql'=>$sql];
    }
    foreach (fullMigrationColumnDefinitions() as $table => $columns) {
        if (!migrationTableExists($table)) continue;
        foreach ($columns as $column => $definition) {
            if (!migrationColumnExists($table, $column)) $plan[] = ['type'=>'column','label'=>'Tambah kolom '.$table.'.'.$column,'sql'=>'ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition];
        }
    }
    foreach (fullMigrationIndexDefinitions() as $table => $indexes) {
        if (!migrationTableExists($table)) continue;
        foreach ($indexes as $index => $definition) {
            if (!migrationIndexExists($table, $index)) $plan[] = ['type'=>'index','label'=>'Tambah indeks '.$table.'.'.$index,'sql'=>'ALTER TABLE `'.$table.'` ADD '.$definition];
        }
    }
    return $plan;
}

function assertAdditiveMigrationSql(string $sql): void {
    $normalized = ltrim($sql);
    if (!preg_match('/^(CREATE TABLE IF NOT EXISTS|ALTER TABLE `?[a-z_]+`? ADD)/i', $normalized)) {
        throw new RuntimeException('Operasi migrasi non-additive ditolak.');
    }
    if (preg_match('/\b(DROP|TRUNCATE|RENAME)\b/i', $normalized)) {
        throw new RuntimeException('Operasi migrasi berisiko ditolak.');
    }
}

function runFullDatabaseMigration(): array {
    $plan = databaseMigrationPlan();
    $applied = [];
    foreach ($plan as $operation) {
        assertAdditiveMigrationSql((string)$operation['sql']);
        db()->exec((string)$operation['sql']);
        $applied[] = (string)$operation['label'];
    }

    // Seed-only operations: INSERT IGNORE never overwrites existing target data.
    if (migrationTableExists('questions')) {
        $stmt = db()->prepare('INSERT IGNORE INTO questions(question_number,is_active) VALUES(?,1)');
        for ($number=1; $number<=10; $number++) $stmt->execute([$number]);
    }
    if (migrationTableExists('app_settings')) {
        $stmt = db()->prepare('INSERT IGNORE INTO app_settings(setting_key,setting_value) VALUES(?,?)');
        foreach (['quiz_open'=>'1','daily_participant_quota'=>(string)DEFAULT_DAILY_PARTICIPANT_QUOTA,'quiz_mode'=>'auto','quiz_start_at'=>'','quiz_end_at'=>''] as $key=>$value) $stmt->execute([$key,$value]);
    }
    if (migrationTableExists('raffle_sequence')) db()->exec('INSERT IGNORE INTO raffle_sequence(id,next_number) VALUES(1,1)');
    if (migrationTableExists('participant_identity_locks') && migrationTableExists('participants')) {
        if (migrationColumnExists('participants','whatsapp')) db()->exec("INSERT IGNORE INTO participant_identity_locks(identity_type,identity_value) SELECT 'whatsapp',whatsapp FROM participants WHERE whatsapp IS NOT NULL AND whatsapp<>''");
        if (migrationColumnExists('participants','tiktok_account')) db()->exec("INSERT IGNORE INTO participant_identity_locks(identity_type,identity_value) SELECT 'tiktok',LOWER(TRIM(LEADING '@' FROM tiktok_account)) FROM participants WHERE tiktok_account IS NOT NULL AND tiktok_account<>''");
        if (migrationColumnExists('participants','device_hash')) db()->exec("INSERT IGNORE INTO participant_identity_locks(identity_type,identity_value) SELECT 'device',device_hash FROM participants WHERE device_hash IS NOT NULL AND device_hash<>''");
    }
    if (migrationTableExists('participants') && migrationColumnExists('participants','name')) {
        $hasNormalizedName = migrationColumnExists('participants','name_normalized');
        $uppercaseMigrationRecorded = false;
        if (migrationTableExists('database_migrations')) {
            $migrationCheck = db()->prepare('SELECT 1 FROM database_migrations WHERE migration_key=? LIMIT 1');
            $migrationCheck->execute(['uppercase_participant_names_2026_07_24']);
            $uppercaseMigrationRecorded = (bool)$migrationCheck->fetchColumn();
        }
        if (!$uppercaseMigrationRecorded) {
            $uppercaseSql = $hasNormalizedName
                ? 'UPDATE participants SET name=UPPER(name), name_normalized=UPPER(name_normalized) WHERE BINARY name<>BINARY UPPER(name) OR BINARY name_normalized<>BINARY UPPER(name_normalized)'
                : 'UPDATE participants SET name=UPPER(name) WHERE BINARY name<>BINARY UPPER(name)';
            db()->exec($uppercaseSql);
            $applied[] = 'Ubah nama peserta lama menjadi uppercase';
            if (migrationTableExists('database_migrations')) {
                db()->prepare('INSERT IGNORE INTO database_migrations(migration_key,description) VALUES(?,?)')
                    ->execute(['uppercase_participant_names_2026_07_24','Normalisasi nama peserta menjadi uppercase tanpa menghapus data']);
            }
        }
    }
    if (migrationTableExists('database_migrations')) {
        db()->prepare('INSERT IGNORE INTO database_migrations(migration_key,description) VALUES(?,?)')
            ->execute(['full_schema_2026_07_24','Migrasi penuh additive-only skema Quiz TikTok']);
    }
    return ['applied'=>$applied,'remaining'=>databaseMigrationPlan()];
}

function databaseMigrationHistory(): array {
    if (!migrationTableExists('database_migrations')) return [];
    return db()->query('SELECT migration_key,description,applied_at FROM database_migrations ORDER BY id DESC LIMIT 10')->fetchAll();
}

function participantUppercaseMigrationPending(): bool {
    if (!migrationTableExists('participants') || !migrationColumnExists('participants','name')) return false;
    if (!migrationTableExists('database_migrations')) return true;
    $stmt = db()->prepare('SELECT 1 FROM database_migrations WHERE migration_key=? LIMIT 1');
    $stmt->execute(['uppercase_participant_names_2026_07_24']);
    return !(bool)$stmt->fetchColumn();
}
