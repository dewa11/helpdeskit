# Operations Notes

## Running migrations

- Backup production database first (e.g., `mysqldump -u user -p dbname > backup.sql`).
- Run migrations in order:
  - `mysql -u user -p dbname < migrations/001_add_nip_and_priority.sql`
  - `mysql -u user -p dbname < migrations/002_add_lifecycle_timestamps.sql`
- If using a remote host, include `-h host` in the mysql command.
- For MySQL versions before 8, `IF NOT EXISTS` is not supported; apply the ALTER statements manually or drop the clause before running.

## Manual verification checklist

- **Login with NIP**: Create a user via Head IT UI (NIP as username), log out, then log in using that NIP and the default password (same as NIP). Reset password to NIP and log in again to confirm.
- **Priority column**: Open Daftar Tiket, set priority as Head IT, reload the page, and confirm badges show the chosen priority. Verify priority select appears in ticket detail when not closed.
- **Lifecycle timestamps**: Create a new ticket via public report form. As Head IT, assign it; as IT staff, Start then Finish. As Head IT, Confirm/Close. Ensure dashboard/tickets list show the Lapor/Dikerjakan/Selesai compact times and ticket detail timeline displays assigned, started, finished, and closed timestamps in order.
- **Upload flow** (optional): When finishing a ticket, upload an image/video and verify it appears in the detail view and remains accessible via the uploads link.
