<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

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

// (privacy:metadata definido en el bloque "Strings del Privacy provider" más abajo.)

// Tareas adhoc (vistas en Administración del sitio > Servidor > Tareas).
$string['task_grade_submission'] = 'AI Grader Pro: calificar una entrega';
$string['errortaskfailed'] = 'La tarea de calificación de AI Grader Pro ha fallado: {$a}';

// Pagina de gestion (/local/aigrader/manage.php).
$string['manage_pagetitle']         = 'AI Grader Pro · {$a}';
$string['manage_heading']           = 'AI Grader Pro: {$a}';
$string['manage_disabled']          = 'AI Grader Pro no esta habilitado en esta tarea. Edita la configuracion de la tarea para activarlo.';
$string['manage_no_submissions']    = 'Aun no hay entregas para esta tarea.';
$string['manage_polling']           = 'Una calificacion esta en proceso. Esta pagina se actualizara automaticamente.';
$string['manage_back_to_assignment'] = '← Volver a la tarea';
$string['msg_enqueued']             = 'Tarea de calificacion IA encolada. Se ejecutara en el proximo cron.';
$string['msg_graded_now']           = 'Calificacion IA completada. Pulsa Revisar → para ver la propuesta.';
$string['msg_needs_manual_review']  = 'La IA no pudo procesar esta entrega automaticamente. Pulsa Revisar → para calificar a mano.';

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

// Pagina de revision (/local/aigrader/review.php).
$string['review_pagetitle']       = 'Revisar propuesta IA · {$a}';
$string['review_heading']         = 'Revisar propuesta IA: {$a->assign} — {$a->student}';
$string['review_submission_text'] = 'Entrega del alumno';
$string['review_proposed']        = 'Nota y feedback propuestos (editables)';
$string['review_criterion_scores'] = 'Puntuacion por criterio (de la IA, informativa)';
$string['review_proposed_at']     = 'Propuesta hecha el {$a}';
$string['review_proposed_by']     = 'por {$a->provider} ({$a->model})';
$string['manualfallback_banner']  = 'La calificación con IA no estuvo disponible para esta entrega, por lo que el formulario está vacío. Rellena nota y feedback manualmente; "Aprobar y publicar" los escribe al gradebook igual que con las propuestas IA. Motivo:';
$string['manualfallback_default'] = 'no hay propuesta IA registrada para esta entrega.';
$string['review_submission_files']        = 'Ficheros adjuntos';
$string['review_submission_seen_by_ai']   = 'Entrega tal y como la vio la IA';

$string['field_finalgrade']         = 'Nota final (0-10)';
$string['field_strengths']          = 'Aciertos';
$string['field_strengths_hint']     = 'Uno por linea. Se mostrara al alumno como feedback positivo.';
$string['field_improvements']       = 'Mejorables';
$string['field_improvements_hint']  = 'Uno por linea. Sugerencias constructivas que vera el alumno.';
$string['field_justification']      = 'Justificacion (visible para el alumno)';

$string['btn_review']          = 'Revisar →';
$string['btn_view_published']  = 'Ver ✓';
$string['btn_approve_publish'] = 'Aprobar y publicar';
$string['btn_reject']          = 'Rechazar (calificar manualmente)';
$string['confirm_reject']      = 'Esto descarta la propuesta IA y tendras que calificar manualmente. Continuar?';

$string['msg_published']      = 'Nota aprobada y publicada en el libro de calificaciones.';
$string['msg_rejected']       = 'Propuesta IA rechazada. Califica manualmente usando la pantalla estandar de la tarea.';

$string['feedback_strengths']    = 'Aciertos';
$string['feedback_improvements'] = 'A mejorar';
$string['feedback_justification'] = 'Resumen';

$string['errornoproposal']      = 'No hay propuesta IA disponible para esta entrega.';
$string['errorparseproposal']   = 'La propuesta IA guardada no se ha podido leer. Recalifica para regenerarla.';
$string['errorgradeoutofrange'] = 'La nota debe estar entre 0 y 10 (recibido: {$a}).';

// Strings del Privacy provider (reemplazan el placeholder de v0.1.0).
$string['privacy:metadata'] = 'AI Grader Pro almacena propuestas de calificacion generadas por IA, registros de auditoria de cada accion, y configuracion por tarea. Tambien se envian datos personales a un proveedor LLM externo via el AI Subsystem de Moodle.';

// Tabla local_aigrader_assign.
$string['privacy:metadata:assign']               = 'Configuracion de AI Grader Pro por tarea (en que tareas esta habilitado, criterios de evaluacion, y overrides). Almacena el id del profesor que edito por ultima vez la configuracion.';
$string['privacy:metadata:assign:assignid']      = 'Id interno de la tarea.';
$string['privacy:metadata:assign:criteria_text'] = 'Criterios de evaluacion escritos por el profesor en lenguaje natural.';
$string['privacy:metadata:assign:usermodified']  = 'Id del profesor que edito por ultima vez la configuracion. Se anonimiza al borrar el usuario.';
$string['privacy:metadata:assign:timecreated']   = 'Momento en que se guardo la configuracion por primera vez.';
$string['privacy:metadata:assign:timemodified']  = 'Momento de la ultima modificacion.';

// Tabla local_aigrader_submission.
$string['privacy:metadata:submission']                   = 'Estado de la calificacion IA por entrega: nota y feedback propuestos, mas la nota y feedback finales aprobados por el profesor.';
$string['privacy:metadata:submission:submissionid']      = 'Id de la entrega de tarea a la que se refiere.';
$string['privacy:metadata:submission:studentid']         = 'Id del alumno cuya entrega ha sido calificada.';
$string['privacy:metadata:submission:status']            = 'Estado actual en la maquina de estados (pending_ai / ai_proposed / teacher_reviewed / published / error).';
$string['privacy:metadata:submission:proposed_grade']    = 'Nota propuesta por la IA (0-10).';
$string['privacy:metadata:submission:proposed_feedback'] = 'Respuesta completa del LLM: puntuaciones por criterio, aciertos, mejorables, justificacion.';
$string['privacy:metadata:submission:final_grade']       = 'Nota aprobada por el profesor (puede diferir de la propuesta si el profesor edito).';
$string['privacy:metadata:submission:final_feedback']    = 'Feedback aprobado por el profesor y mostrado al alumno.';
$string['privacy:metadata:submission:final_grader']      = 'Id del profesor que aprobo la nota. Se anonimiza al borrar el usuario.';
$string['privacy:metadata:submission:timecreated']       = 'Momento en que se encolo la primera calificacion IA.';
$string['privacy:metadata:submission:timemodified']      = 'Momento de la ultima modificacion.';
$string['privacy:metadata:submission:timeprocessed']     = 'Momento en que termino la llamada al LLM.';
$string['privacy:metadata:submission:timepublished']     = 'Momento en que el profesor aprobo y la nota se escribio en el gradebook.';

// Tabla local_aigrader_log.
$string['privacy:metadata:log']                = 'Registro append-only de cada accion de calificacion IA. Exigido por el AI Act (Reg. 2024/1689 Anexo III) para sistemas IA de alto riesgo en educacion.';
$string['privacy:metadata:log:userid']         = 'Id del profesor que disparo la accion. Se anonimiza al borrar el usuario.';
$string['privacy:metadata:log:studentid']      = 'Id del alumno cuya entrega se proceso.';
$string['privacy:metadata:log:action']         = 'Tipo de accion registrada (grade, regrade, edit, approve, reject).';
$string['privacy:metadata:log:llm_provider']   = 'Nombre del proveedor LLM utilizado (ej. openai, azureai).';
$string['privacy:metadata:log:llm_model']      = 'Identificador del modelo LLM utilizado (ej. llama-3.3-70b-versatile).';
$string['privacy:metadata:log:prompt_text']    = 'Prompt completo enviado al LLM (incluye el texto de la entrega del alumno).';
$string['privacy:metadata:log:response_json']  = 'Respuesta cruda del LLM en JSON (incluye nota propuesta y feedback).';
$string['privacy:metadata:log:tokens_input']   = 'Numero de tokens de entrada consumidos por la llamada al LLM.';
$string['privacy:metadata:log:tokens_output']  = 'Numero de tokens de salida consumidos por la llamada al LLM.';
$string['privacy:metadata:log:proposed_grade'] = 'Nota propuesta por el LLM en el momento de la accion.';
$string['privacy:metadata:log:final_grade']    = 'Nota final tras revision del profesor (si aplica).';
$string['privacy:metadata:log:teacher_edits']  = 'JSON diff que muestra como el profesor modifico la propuesta IA.';
$string['privacy:metadata:log:timecreated']    = 'Momento en que se registro la accion.';

// Proveedor LLM externo (datos transferidos fuera de Moodle).
$string['privacy:metadata:ai_subsystem']             = 'AI Grader Pro envia el texto de la entrega del alumno junto con los criterios de evaluacion del profesor al proveedor LLM configurado en el AI Subsystem de Moodle. El proveedor puede estar alojado dentro o fuera de la UE segun la eleccion de la institucion. El administrador del sitio firma un Data Processing Agreement (DPA) con el proveedor elegido.';
$string['privacy:metadata:ai_subsystem:prompt_text'] = 'Texto de la entrega del alumno junto con los criterios e instrucciones de calificacion del profesor.';
$string['privacy:metadata:ai_subsystem:userid']      = 'Identificador de usuario pasado al proveedor LLM para rate-limiting y prevencion de abuso (aplica la politica de privacidad del proveedor).';

// Banner de errores clasificados (solo profesor, nunca al alumno).
$string['err_banner_title']         = 'La calificacion con IA fallo';
$string['err_banner_title_plural']  = 'La calificacion con IA fallo en {$a} entregas';
$string['err_banner_affecting']     = 'Afecta a: {$a}';
$string['err_banner_show_details']  = 'Ver error tecnico';
$string['err_banner_retry']         = 'Reintentar ahora';

// Payload demasiado grande.
$string['err_payload_too_large_headline'] = 'La entrega supera el limite del modelo';
$string['err_payload_too_large_body']     = 'La entrega ocupa {$a->requested} tokens pero el modelo configurado "{$a->model}" solo acepta {$a->limit} tokens por minuto en el plan actual.';
$string['err_payload_too_large_body_partial'] = 'La entrega supero el limite de tokens por minuto del modelo configurado.';
$string['err_payload_too_large_action']   = 'Cambia a un modelo con mayor limite TPM en Administracion del sitio -> IA -> Proveedores, o pide al alumno que elimine los outputs del notebook antes de volver a entregar.';

// No autorizado.
$string['err_unauthorized_headline'] = 'El proveedor rechazo la API key';
$string['err_unauthorized_body']     = 'El proveedor LLM devolvio un error de autenticacion. La API key no existe, es invalida o ha sido revocada.';
$string['err_unauthorized_action']   = 'Ve a Administracion del sitio -> IA -> Proveedores y revisa la API key del proveedor activo.';

// Rate limit.
$string['err_rate_limited_headline'] = 'Limite de peticiones por minuto superado';
$string['err_rate_limited_body']     = 'Se han enviado demasiadas peticiones de calificacion en poco tiempo. Moodle reintentara automaticamente con backoff exponencial.';
$string['err_rate_limited_action']   = 'No hay nada que hacer. La calificacion se reanudara cuando se libere la cuota.';

// Error 5xx del proveedor.
$string['err_provider_error_headline'] = 'Error temporal del proveedor';
$string['err_provider_error_body']     = 'El proveedor LLM devolvio un error de servidor temporal. Moodle reintentara automaticamente.';
$string['err_provider_error_action']   = 'No hay nada que hacer. Si el problema persiste mas de 15 minutos, revisa la pagina de estado del proveedor.';

// Error de red.
$string['err_network_error_headline'] = 'No se pudo conectar con el proveedor LLM';
$string['err_network_error_body']     = 'La conexion con el proveedor LLM fallo (timeout, error de DNS o conexion rechazada).';
$string['err_network_error_action']   = 'Revisa la conectividad de red del sitio y la URL del endpoint del proveedor. Moodle reintentara automaticamente.';

// Error de parseo.
$string['err_parse_error_headline'] = 'El LLM devolvio una respuesta no valida';
$string['err_parse_error_body']     = 'El modelo produjo una salida que no se pudo parsear al formato JSON de calificacion esperado.';
$string['err_parse_error_action']   = 'Pulsa "Reintentar ahora" para volver a llamar al modelo. Si el problema persiste, los criterios pueden estar invitando a respuestas en prosa libre; revisa los criterios de evaluacion.';

// Desconocido (catch-all).
$string['err_unknown_headline'] = 'La calificacion con IA fallo';
$string['err_unknown_body']     = 'El proveedor devolvio un error: {$a}';
$string['err_unknown_action']   = 'Consulta los detalles en el audit log y vuelve a intentarlo.';
