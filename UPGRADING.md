# Upgrading

## 2.x.x to 3.x.x
The database changed. In order to update the database:

    $ sudo -u apache sqlite3 /var/lib/vpn-user-portal/configurations.sqlite

Copy/paste the following:

    CREATE TABLE IF NOT EXISTS new_config (
        user_id VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        status INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        revoked_at INTEGER DEFAULT NULL,
        UNIQUE (user_id, name)
    );

Now, transfer all the old data to the new table:

    INSERT INTO new_config SELECT user_id, name, status, 0, NULL FROM config;

Drop the old table, and rename the newly created one back to the old name:

    DROP TABLE config;
    ALTER TABLE new_config RENAME TO config;

That should be all! 
