# Upgrading

## 3.x.x to 4.x.x
The database changed!

The state `READY` is no longer supported in the database, so all configurations
in this state should be removed. The ready state means that configurations were
'created' in the user portal, but not yet downloaded and activated in the 
CA. This does not make sense, and thus we got rid of that state. If 
configurations where is this state, the user can simply create a new one with
the same name.

Connect to the database:

    $ sudo -u apache sqlite3 /var/lib/vpn-user-portal/configurations.sqlite

Perform the following query:

    DELETE FROM config WHERE state = 10;

## 2.x.x to 3.x.x
The database changed!

**NOTE**: all configurations that were created, but not yet downloaded will
be unavailable! This should not be a big problem, they can be marked as 
'revoked' in this upgrade as to not let them be in a state of limbo. The user
did not download it, so does not have it, therefore it does not need to be 
revoked in the backend!

Connect to the database:

    $ sudo -u apache sqlite3 /var/lib/vpn-user-portal/configurations.sqlite

Mark all configurations as revoked, in case they were in 'ready' state, i.e. 
created but not yet downloaded:

    UPDATE config SET config=NULL, status = 30 WHERE status = 10;

Copy/paste the following:

    CREATE TABLE IF NOT EXISTS new_config (
        user_id VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        status INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        revoked_at INTEGER DEFAULT NULL,
        UNIQUE (user_id, name)
    );

Now, transfer all the old data to the new table, we set the creation time to
timestamp 0:

    INSERT INTO new_config SELECT user_id, name, status, 0, NULL FROM config;

For all revoked configurations, we also update their revocation date back to
timestamp 0:

    UPDATE new_config SET revoked_at = 0 WHERE status = 30;

Drop the old table, and rename the new created one back to the old name:

    DROP TABLE config;
    ALTER TABLE new_config RENAME TO config;
