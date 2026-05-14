# AI Grader Pro (local_aigrader)

Plugin de Moodle 4.5 LTS que añade calificación asistida por IA al módulo de tareas (`mod_assign`).

**Estado**: v0.1.0-alpha — solo esqueleto. No tiene funcionalidad aún.

## Decisiones arquitectónicas

Ver `docs/architecture-decision-001.md` en la raíz del proyecto.

## Estructura

```
local/aigrader/
├── version.php                          Metadata del plugin
├── lang/en/local_aigrader.php           Strings inglés
├── lang/es/local_aigrader.php           Strings español
├── db/access.php                        Capabilities
├── classes/privacy/provider.php         GDPR (null por ahora)
└── README.md                            Este archivo
```

## Instalación (durante desarrollo)

El plugin vive directamente en `moodle/local/aigrader/`. Moodle lo detectará al
visitar la página de admin y pedirá hacer upgrade.

Para instalar vía CLI sin pasar por la UI:

```bash
cd ~/moodle-dev/moodle-docker
bin/moodle-docker-compose exec webserver php admin/cli/upgrade.php --non-interactive
```

## Capabilities

| Capability | Propósito | Roles permitidos por defecto |
|---|---|---|
| `local/aigrader:use` | Usar AI grading en una entrega | editingteacher, manager |
| `local/aigrader:configure` | Configurar plugin per-tarea | editingteacher, manager |
| `local/aigrader:viewlog` | Ver log de auditoría completo | manager |

## Siguiente paso

Ver el roadmap en `docs/architecture-decision-001.md` sección 6. El siguiente
componente a construir es la integración con `mod_assign` vía Hooks API y el
form de configuración per-tarea.
