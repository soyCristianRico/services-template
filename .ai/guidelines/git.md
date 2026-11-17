# Git

- Commit only when the user explicitly asks (see permissions).
- Commit to the current branch (usually `main`). Do NOT create a new branch for
  commits unless the user explicitly requests it.

## A push is a deploy

- A push to `main` goes to production on its own: the server deploys on every push.
  There is no separate deploy step to ask about.
- So when the user approves a change ("adelante", "súbelo", "haz commit"), commit **and
  push** in the same step. Do not stop to ask whether to push.
- The push is the release: run the affected tests and `composer run format` before it,
  and say in the reply that it is on its way to production.
- Exception: the commits `sync-guias.sh aplicar` leaves in every project are never pushed
  together — pushing them all at once brings the deploy server down. Each project takes them
  up with its own next push.
