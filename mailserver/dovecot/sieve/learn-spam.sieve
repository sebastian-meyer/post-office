# /var/mail/virtual/default/sieve/learn-spam.sieve

require ["vnd.dovecot.pipe", "copy", "imapsieve"];

pipe :copy "rspamc" ["learn_spam"];
