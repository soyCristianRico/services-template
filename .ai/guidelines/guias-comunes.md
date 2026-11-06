# Guías comunes

- Las guías de `.ai/guidelines/` que aparecen en `.ai/guidelines/.sync` vienen del repositorio
  de guías (`~/proyectos/guias`, `soyCristianRico/guias`). Ahí está la versión buena: aquí hay
  una copia.
- El resto de ficheros de `.ai/guidelines/` (los que no están en `.sync`, como
  `styling-proyecto.md`) son propios de este proyecto y se editan aquí con normalidad.
- Si Claude no programa como queremos y la corrección vale para todos los proyectos, corrige la
  guía común aquí mismo y, al terminar, súbela y repártela:
  1. `~/proyectos/guias/sync-guias.sh traer <este-proyecto> <guia.md>`
  2. Commit y push en `~/proyectos/guias`.
  3. `~/proyectos/guias/sync-guias.sh aplicar` (copia a todos, regenera cada CLAUDE.md y hace
     commit en cada proyecto). Nunca push de todos a la vez: cada proyecto se sube cuando toque.
- Si la corrección solo vale para este proyecto, no toques la guía común: escríbela en una guía
  propia (`<tema>-proyecto.md`).
- Nunca edites el CLAUDE.md a mano: se genera con `php artisan boost:update` a partir de
  `.ai/guidelines/`.
