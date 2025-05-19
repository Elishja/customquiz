<?php

require(__DIR__.'/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/question/engine/lib.php');
require_once($CFG->libdir . '/question/engine/questionusage.php');
require_once($CFG->libdir . '/question/engine/displayoptions.php');

$qubaid = required_param('qubaid', PARAM_INT);

$quba = question_engine::load_questions_usage_by_activity($qubaid);
$contextid = $quba->get_owning_context()->id;
$context = context::instance_by_id($contextid);
$customquizid = $context->instanceid;

require_login();
require_capability('mod/customquiz:view', $context);

// Procesar respuestas
question_engine::save_questions_usage_by_activity($quba);
$quba->process_all_actions(time());

// Calcular puntuación y aciertos
$totalmark = $quba->get_total_mark();
$questioncount = $quba->get_question_count();
$correctcount = 0;

for ($slot = 1; $slot <= $questioncount; $slot++) {
    $qa = $quba->get_question_attempt($slot);
    if ($qa->is_correct()) {
        $correctcount++;
    }
}

// Guardar intento en BD
$record = (object)[
    'customquizid' => $customquizid,
    'userid' => $USER->id,
    'qubaid' => $qubaid,
    'score' => $totalmark,
    'questioncount' => $questioncount,
    'correctcount' => $correctcount,
    'status' => 'finalizado',
    'timecreated' => time()
];

$DB->insert_record('customquiz_attempts', $record);

// Página de resultados
$PAGE->set_url('/mod/customquiz/submit.php');
$PAGE->set_context($context);
$PAGE->set_title('Resultados del intento');
$PAGE->set_heading('Resultados del intento');

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo html_writer::tag('h2', 'Resultado del intento');
echo html_writer::tag('p', 'Puntuación total: ' . round($totalmark, 2));
echo html_writer::tag('p', "Respuestas correctas: $correctcount de $questioncount");
echo $OUTPUT->box_end();

// Mostrar preguntas con feedback
$displayoptions = new question_display_options();
$displayoptions->feedback = question_display_options::VISIBLE;
$displayoptions->correctness = question_display_options::VISIBLE;
$displayoptions->marks = question_display_options::MARKS_ALL;
$displayoptions->generalfeedback = question_display_options::VISIBLE;

for ($slot = 1; $slot <= $questioncount; $slot++) {
    echo html_writer::tag('div', $quba->render_question($slot, $PAGE, $displayoptions), ['class' => 'question-review']);
}

echo $OUTPUT->footer();
