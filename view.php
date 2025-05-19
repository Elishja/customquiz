<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$c = optional_param('c', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('customquiz', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('customquiz', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('customquiz', ['id' => $c], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('customquiz', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

$PAGE->set_url('/mod/customquiz/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo html_writer::tag('h2', 'Test a la carta');

// Mostrar solo las categorías permitidas
$allowed = explode(',', $moduleinstance->allowedcategories);
$allowed = array_map('intval', $allowed);

$placeholders = implode(',', array_fill(0, count($allowed), '?'));
$categories = $DB->get_records_select('question_categories', "id IN ($placeholders)", $allowed);

echo html_writer::start_tag('form', ['method' => 'get', 'action' => 'attempt.php']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cm->id]);

$options = '';
foreach ($categories as $cat) {
    $options .= html_writer::tag('option', $cat->name, ['value' => $cat->id]);
}
echo html_writer::tag('label', 'Selecciona una o más categorías:', ['for' => 'categories']);
echo html_writer::tag('select', $options, [
    'name' => 'categories[]',
    'multiple' => 'multiple',
    'size' => 10,
    'style' => 'width: 100%;'
]);

echo html_writer::empty_tag('br');
echo html_writer::tag('label', 'Número de preguntas:', ['for' => 'count']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'count',
    'value' => $moduleinstance->minquestions,
    'min' => $moduleinstance->minquestions,
    'max' => $moduleinstance->maxquestions
]);

echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Comenzar intento']);
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

// Mostrar historial como tabla
$attempts = $DB->get_records('customquiz_attempts', [
    'userid' => $USER->id,
    'customquizid' => $moduleinstance->id
]);

if ($attempts) {
    echo $OUTPUT->heading('Tus intentos anteriores');
    echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width:100%']);

    // Cabecera
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', 'Fecha') .
        html_writer::tag('th', 'Nota') .
        html_writer::tag('th', 'Correctas') .
        html_writer::tag('th', 'Total') .
        html_writer::tag('th', 'Estado') .
        html_writer::tag('th', 'Acciones')
    );
    echo html_writer::end_tag('thead');

    // Cuerpo
    echo html_writer::start_tag('tbody');
    foreach ($attempts as $a) {
        $reviewlink = new moodle_url('/mod/customquiz/review.php', ['qubaid' => $a->qubaid]);
        echo html_writer::tag('tr',
            html_writer::tag('td', userdate($a->timecreated)) .
            html_writer::tag('td', round($a->score, 2)) .
            html_writer::tag('td', $a->correctcount ?? '-') .
            html_writer::tag('td', $a->questioncount ?? '-') .
            html_writer::tag('td', ucfirst($a->status ?? 'Finalizado')) .
            html_writer::tag('td', html_writer::link($reviewlink, 'Ver intento'))
        );
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
