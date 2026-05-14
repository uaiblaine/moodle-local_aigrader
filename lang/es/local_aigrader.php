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

// Form de edición de la tarea (mod_assign).
$string['form_enabled'] = 'Habilitar calificación asistida por IA en esta tarea';
$string['form_enabled_help'] = 'Cuando está marcado, los profesores pueden lanzar AI Grader Pro sobre las entregas de esta tarea. La IA propone nota y feedback; el profesor revisa y decide. Nada se publica al alumno hasta que el profesor aprueba.';

$string['form_criteria'] = 'Criterios de evaluación';
$string['form_criteria_help'] = 'Descripción en lenguaje natural de cómo debe evaluar la IA las entregas de esta tarea. Escribe las mismas instrucciones que darías a un becario. Menciona los criterios concretos, su peso relativo, y el tono de feedback que quieres. Ejemplo:

Evalúa este ensayo (800-1000 palabras) sobre digitalización educativa según estos criterios:
- Claridad de la tesis (25%): ¿es la postura defendible?
- Calidad de evidencias (30%): ¿las fuentes son académicas y bien citadas?
- Estructura (25%): introducción, desarrollo, conclusión
- Lenguaje (20%): registro académico, ortografía

Tono: constructivo y específico, en español.';

$string['form_criteria_imported_notice'] = 'Criterios pre-rellenados desde la rúbrica configurada en "Calificación > Calificación avanzada". Puedes editarlos antes de habilitar la calificación IA.';

$string['form_model_override'] = 'Modelo (opcional)';
$string['form_model_override_help'] = 'Si lo defines, esta tarea usa este modelo específico en lugar del default del provider IA. Útil cuando quieres un modelo más capaz (o más barato) para una tarea concreta. Déjalo vacío para usar el default global.';

$string['form_language_override'] = 'Idioma del feedback (opcional)';
$string['form_language_override_help'] = 'Si lo defines, el feedback de la IA para esta tarea irá en este idioma en lugar del idioma del curso. Déjalo en "Auto" para usar el idioma del curso.';

$string['form_lang_auto'] = 'Auto (usar idioma del curso)';

// Errores de validación.
$string['error_criteria_required'] = 'Los criterios de evaluación son obligatorios cuando la calificación asistida por IA está habilitada. Describe cómo debe evaluar la IA las entregas.';

// Importador de rúbricas.
$string['rubric_export_header'] = 'Criterios (auto-importados de la rúbrica de calificación avanzada de la tarea):';

// Privacidad.
$string['privacy:metadata'] = 'AI Grader Pro almacena registros de auditoría de las acciones de calificación asistida por IA, incluyendo prompts enviados al proveedor LLM configurado, respuestas del modelo, notas propuestas y ediciones del profesor. Consulta la documentación del plugin para más detalles.';

// Tareas adhoc (vistas en Administración del sitio > Servidor > Tareas).
$string['task_grade_submission'] = 'AI Grader Pro: calificar una entrega';
$string['errortaskfailed'] = 'La tarea de calificación de AI Grader Pro ha fallado: {$a}';

// Pagina de gestion (/local/aigrader/manage.php).
$string['manage_pagetitle']         = 'AI Grader Pro · {$a}';
$string['manage_heading']           = 'AI Grader Pro: {$a}';
$string['manage_disabled']          = 'AI Grader Pro no esta habilitado en esta tarea. Edita la configuracion de la tarea para activarlo.';
$string['manage_no_submissions']    = 'Aun no hay entregas para esta tarea.';
$string['manage_polling']           = 'Una calificacion esta en proceso. Esta pagina se actualizara automaticamente.';
$string['manage_back_to_assignment']= '← Volver a la tarea';
$string['msg_enqueued']             = 'Tarea de calificacion IA encolada. Se ejecutara en el proximo cron.';

$string['th_student']   = 'Alumno';
$string['th_submitted'] = 'Entregado';
$string['th_status']    = 'Estado IA';
$string['th_grade']     = 'Nota propuesta';
$string['th_action']    = 'Accion';

$string['btn_grade_with_ai']   = 'Calificar con IA';
$string['btn_regrade_with_ai'] = 'Recalificar con IA';
$string['btn_pending']         = 'Procesando...';

$string['status_none']        = 'Sin calificacion IA';
$string['status_pending']     = 'Pendiente';
$string['status_proposed']    = 'Propuesta IA';
$string['status_reviewed']    = 'Revisada por profesor';
$string['status_published']   = 'Publicada';
$string['status_error']       = 'Error';
$string['status_unsupported'] = 'Formato no soportado';

$string['errornotenabled']  = 'AI Grader Pro no esta habilitado en esta tarea.';
$string['errornocriteria']  = 'No hay criterios de evaluacion definidos para esta tarea.';
