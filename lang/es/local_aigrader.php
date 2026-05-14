<?php
/**
 * Spanish language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Descripciones de capabilities (vistas en Administración del sitio > Permisos).
$string['aigrader:use'] = 'Usar calificación asistida por IA en tareas';
$string['aigrader:configure'] = 'Configurar AI Grader Pro en una tarea';
$string['aigrader:viewlog'] = 'Ver registro de auditoría de AI Grader Pro';

// Página de configuración del admin.
$string['setting_enabled'] = 'Habilitar plugin';
$string['setting_enabled_desc'] = 'Interruptor global de AI Grader Pro. Cuando está desactivado, los profesores no pueden lanzar nuevas calificaciones IA en ninguna tarea. Los registros de auditoría existentes se conservan.';

$string['setting_rubric_autoimport'] = 'Auto-importar desde rúbrica de calificación';
$string['setting_rubric_autoimport_desc'] = 'Cuando una tarea usa el método de calificación por rúbrica de Moodle, pre-rellena automáticamente los criterios de evaluación de AI Grader Pro con el contenido de la rúbrica. El profesor puede editar los criterios importados antes de habilitar la calificación IA.';

$string['setting_default_system_prompt'] = 'Prompt de sistema por defecto';
$string['setting_default_system_prompt_desc'] = 'Instrucción institucional opcional que se añade al system prompt de cada solicitud de calificación. Útil para imponer tono o política consistentes entre todos los profesores. Ejemplo: "Aporta feedback constructivo en registro académico, máximo 200 palabras." Déjalo vacío para usar solo el system prompt por defecto del plugin.';

// Privacidad.
$string['privacy:metadata'] = 'AI Grader Pro almacena registros de auditoría de las acciones de calificación asistida por IA, incluyendo prompts enviados al proveedor LLM configurado, respuestas del modelo, notas propuestas y ediciones del profesor. Consulta la documentación del plugin para más detalles.';
