#!/usr/bin/env python3

# /usr/local/bin/postfix-custom-policyd.py

# Search for everything in square brackets. Replace those with the actual values for your setup.

# Policy service for Postfix: rejects emails to "members only" mailing lists from senders who are not members.
# Requires the MySQL client library for Python (Debian/Ubuntu: python3-mysqldb).
import socketserver
import MySQLdb

def check_database_policy(sender, recipient):
    conn = None
    try:
        # Establish MariaDB connection parameters
        conn = MySQLdb.connect(
            host="127.0.0.1",
            user="vmail",
            passwd="[PASSWORD]",
            db="vmail",
            autocommit=True # Ensures changes or connections refresh properly
        )
        cursor = conn.cursor()

        query = """
            SELECT 'REJECT Only members are allowed to post to this list.'
            FROM accounts t1
            WHERE t1.username=SUBSTRING_INDEX(SUBSTRING_INDEX(%s,'@',1),'+',1) AND t1.domain=SUBSTRING_INDEX(%s,'@',-1) AND t1.type='list' AND t1.strictlist=true AND t1.enabled=true AND NOT EXISTS
                (SELECT 1 FROM account_destinations t2 WHERE t2.account=t1.id AND t2.destination=%s)
            LIMIT 1
        """
        cursor.execute(query, (recipient, recipient, sender))
        row = cursor.fetchone()

        if row:
            return row[0] # fetchone returns a tuple, grab the first element

    except Exception as e:
        print(f"MariaDB Database Error: {e}")
    finally:
        if conn:
            conn.close()

    return None

class PostfixPolicyHandler(socketserver.StreamRequestHandler):
    def handle(self):
        attrs = {}
        # Postfix sends attributes line by line, ending with an empty line
        for line in self.rfile:
            line = line.decode('utf-8').strip()
            if not line:
                break
            if '=' in line:
                key, val = line.split('=', 1)
                attrs[key] = val

        # Extract sender and recipient
        sender = attrs.get('sender', '').lower()
        recipient = attrs.get('recipient', '').lower()

        db_action = check_database_policy(sender, recipient)

        if db_action:
            response = f"action={db_action}\n\n"
        else:
            response = "action=DUNNO\n\n"

        self.wfile.write(response.encode('utf-8'))

if __name__ == "__main__":
    # Listen locally on port 12345
    socketserver.ThreadingTCPServer.allow_reuse_address = True
    server = socketserver.ThreadingTCPServer(('127.0.0.1', 12345), PostfixPolicyHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
