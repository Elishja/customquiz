<?php

require(__DIR__.'/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/question/engine/lib.php');
require_once($CFG->libdir . '/question/engine/questionusage.php');
require_once($CFG->libdir . '/question/engine/displayoptions.php');

// Obtener qubaid enviado por URL
$qubaid = required_param('qubaid', PARAM_INT);

// Cargar el intento desde la API
$quba = question_engine::load_questions_usage_by_activity($qubaid);

// Determinar el contexto del intento
$contextid = $quba->get_owning_context()->id;
$context = context::instance_by_id($contextid);

// Verificación de acceso
require_login();
require_capability('mod/customquiz:view', $context);

// Página
$PAGE->set_url('/mod/customquiz/review.php', ['qubaid' => $qubaid]);
$PAGE->set_context($context);
$PAGE->set_title('Revisión del intento');
$PAGE->set_heading('Revisión del intento');

echo $OUTPUT->header();
echo $OUTPUT->heading('Respuestas y corrección');

// Opciones de visualización
$displayoptions = new question_display_options();
$displayoptions->feedback = question_display_options::VISIBLE;
$displayoptions->correctness = question_display_options::VISIBLE;
$displayoptions->marks = question_display_options::MARKS_ALL;
$displayoptions->generalfeedback = question_display_options::VISIBLE;

// Mostrar todas las preguntas del intento
for ($slot = 1; $slot <= $quba->get_question_count(); $slot++) {
    echo html_writer::tag('div', $quba->render_question($slot, $PAGE, $displayoptions), ['class' => 'question-review']);
}

echo $OUTPUT->footer();
