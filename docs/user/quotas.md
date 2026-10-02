# Quotas

Both the module switch and the form switch default to off. With either off, a response completes as it did before and the counter does not move.

A quota is an integer target and a logic rule. The rule can use answers, the assigned arm, and panel attributes. A fully anonymous form cannot use a panel attribute. A parent quota must also have room. A wave quota counts only that wave.

The check runs when the chosen page is left, and again on submit. The server result is the one that counts. The place is taken inside the same transaction as the answer, with the counter row locked. One hundred overlapping submits with ten places left finish with ten completed and ninety over quota.

Over quota keeps the partial answers. The outcome is `over_quota`. That response is not a completed questionnaire, is not scored, and does not send the completion email. Resume stays closed.

Reservations are off unless the quota turns them on. The hold is then 60 minutes unless the quota sets another length. `php yii thiscovery-forms/quota/expire` deletes expired holds. A person who comes back after the hold has expired is checked again.

Actions when the cell is full:

- End, and show a message.
- Redirect to an HTTPS address on the form’s allowlist. The page includes a link as well as the redirect. `{answer}` is left blank on a fully anonymous form.
- Go to another page and keep the response open.
- Mark and continue. The response can finish and does not take a place.

Lowering a target does not remove people who already finished. The fill percent can go over 100. Editing a completed response does not free a place and does not turn that response into over quota, whether or not arm randomisation is on. A finished response is stored as complete so later edits can tell it was already counted. `php yii thiscovery-forms/quota/reconcile` prints any difference and, with `--apply=1`, sets the counter to the recount.

An optional least-filled arm uses the open arm quotas. If every arm the person qualifies for is full, the fullest quota’s action runs and the arm is not kept.

`quota.full` is written to the quota audit when the target is reached. If the form has an address under Email when a quota fills, that address is sent a message. The event is not posted to another system. Webhooks are a later release.
