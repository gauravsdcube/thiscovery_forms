# Secure send

Secure send is off until an administrator turns it on under **Administration → Modules → Thiscovery Forms → Configure**. When it is on, **Settings → Secure send** appears for form managers.

Use it to give a named contact a frozen file without giving them an account.

## Prepare a file

A form can hold many files. Each file has one contact: a name and an email address.

- **Prepare from answers** freezes a CSV from the current answers, including the column headings and the include options you choose. From **Answers**, **Prepare a secure file from these choices** brings those choices here.
- **Upload a file** accepts a CSV, Excel, PDF, JSON, text, or zip file up to 20 MB that you made elsewhere.

The file does not change after it is prepared. Copy the link when it is shown. It is not shown again. Send that link to the contact yourself. If you lose it, **Issue a new link**. The previous link and code stop working.

## Code

**Code lifetime** is set on the form, from 5 minutes to 7 days. The next code uses the current value. A code already sent keeps the expiry it was given.

**Send a new code** emails a one-time code to the contact. The code is not shown to managers. A failed email does not leave a live code, and it does not cancel the previous one.

The contact opens the link, asks for a code, and enters it. They can ask for another code from that page. Asking again, or sending a new code, cancels the previous code. After a download, the same file can be downloaded again when a new code is sent.

**Revoke code** cancels the current code and leaves the file in place. **Revoke file** stops the link. The audit stays.

Changing the contact's email address issues a new link and cancels the current code. Changing only the name does not.

## Audit

Each file has its own audit: when it was prepared, contact changes, links, codes sent or revoked, rejected codes, and each download. A download record includes the time, the IP address, and the browser. These describe the connection that took the file.

Turning Secure send off in Forms configuration stops every existing link.
