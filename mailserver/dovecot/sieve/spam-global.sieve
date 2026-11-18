# /var/mail/virtual/default/sieve/spam-global.sieve

require "fileinto";

if header :contains "X-Spam-Flag" "YES" {
    fileinto "INBOX.Junk";
}

if header :is "X-Spam" "Yes" {
    fileinto "INBOX.Junk";
}
