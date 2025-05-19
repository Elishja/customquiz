<?php
require(__DIR__.'/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/question/engine/lib.php');
require_once($CFG->libdir . '/question/engine/questionusage.php');
require_once($CFG->libdir . '/question/engine/displayoptions.php');

$cmid = required_param('cmid', PARAM_INT);
$categoryids = required_param_array('categories', PARAM_INT);
$count = required_param('count', PARAM_INT);

$cm = get_coursemodule_from_id('customquiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$customquiz = $DB->get_record('customquiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/customquiz:view', $context);

$PAGE->set_url('/mod/customquiz/attempt.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title('Intento de Test a la Carta');
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

// Validar número de preguntas
if ($count < $customquiz->minquestions || $count > $customquiz->maxquestions) {
    echo $OUTPUT->notification("El número de preguntas debe estar entre {$customquiz->minquestions} y {$customquiz->maxquestions}.", 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

// Filtrar categorías permitidas
$allowed = array_map('intval', explode(',', $customquiz->allowedcategories));
$categoryids = array_filter($categoryids, fn($id) => in_array($id, $allowed));

if (empty($categoryids)) {
    echo $OUTPUT->notification('No seleccionaste ninguna categoría permitida.', 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

// Cargar preguntas base
$questionpool = [];
foreach ($categoryids as $catid) {
    $questions = $DB->get_records('question', [
        'category' => $catid,
        'hidden' => 0,
        'parent' => 0
    ]);
    $questionpool = array_merge($questionpool, $questions);
}

// Filtros personalizados según el historial del usuario
if ($customquiz->onlyunanswered || $customquiz->onlyincorrect) {
    $userattempts = $DB->get_records('customquiz_attempts', [
        'userid' => $USER->id,
        'customquizid' => $customquiz->id
    ]);

    $attemptedids = [];
    $incorrectids = [];

    foreach ($userattempts as $attempt) {
        $quba = question_engine::load_questions_usage_by_activity($attempt->qubaid);

        for ($slot = 1; $slot <= $quba->get_question_count(); $slot++) {
            $question = $quba->get_question($slot);
            $questionid = $question->id;

            $attemptobj = $quba->get_question_attempt($slot);
            $attemptedids[] = $questionid;

            if (!$attemptobj->is_correct()) {
                $incorrectids[] = $questionid;
            }
        }
    }

    // Aplicar los filtros
    $questionpool = array_filter($questionpool, function($q) use ($customquiz, $attemptedids, $incorrectids) {
        if ($customquiz->onlyunanswered && in_array($q->id, $attemptedids)) {
            return false;
        }
        if ($customquiz->onlyincorrect && !in_array($q->id, $incorrectids)) {
            return false;
        }
        return true;
    });
}

if (count($questionpool) < $count) {
    echo $OUTPUT->notification('No hay suficientes preguntas disponibles con los filtros aplicados.', 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

// Seleccionar preguntas aleatorias
shuffle($questionpool);
$selectedquestions = array_slice($questionpool, 0, $count);

// Crear intento
$quba = question_engine::make_questions_usage_by_activity('mod_customquiz', $context);
$quba->set_preferred_behaviour($customquiz->attemptbehaviour);

$i = 1;
foreach ($selectedquestions as $q) {
    $questiondata = question_bank::load_question($q->id);
    $quba->add_question($questiondata, $i++);
}

question_engine::save_questions_usage_by_activity($quba);
$_SESSION['customquiz_qubaid'] = $quba->get_id();

// Mostrar preguntas
echo html_writer::start_tag('form', ['method' => 'post', 'action' => 'submit.php']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'qubaid', 'value' => $quba->get_id()]);

$displayoptions = new question_display_options();
$displayoptions->marks = question_display_options::MARKS_ALL;
$displayoptions->feedback = question_display_options::VISIBLE;
$displayoptions->correctness = question_display_options::VISIBLE;

for ($slot = 1; $slot <= $quba->get_question_count(); $slot++) {
    echo html_writer::tag('div', $quba->render_question($slot, $PAGE, $displayoptions), ['class' => 'question']);
}

echo html_writer::empty_tag('br');
echo html_writer::tag('button', 'Enviar intento', ['type' => 'submit']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
