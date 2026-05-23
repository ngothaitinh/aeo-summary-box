# CI/CD Deploy

Push lên branch `main` → GitHub Actions tự động SFTP lên tpiland.com.

## Required GitHub Secrets

| Secret | Mô tả |
|--------|--------|
| `FTP_SERVER` | ftp.tpiland.com |
| `FTP_USERNAME` | claudecode@tpiland.com |
| `FTP_PASSWORD` | (FTP password) |

## Server path
`/public_html/wp-content/plugins/aeo-summary-box/`

## Manual trigger
GitHub → Actions → "Deploy Plugin to tpiland.com" → Run workflow
