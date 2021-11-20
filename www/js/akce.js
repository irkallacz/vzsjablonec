function doTheTrick() {
	$('#deatline').toggle(Boolean($('#frm-akceForm-login_mem').attr('checked')) || Boolean($('#frm-akceForm-login_org').attr('checked')));
}

function getDateTimeInput(elementId) {
	return new Date(document.getElementById(elementId + '-date').value + 'T' + document.getElementById(elementId + '-time').value);
}

function changeDateTimeInput(elementId, diff) {
	datetime = getDateTimeInput(elementId).getTime();
	datetime = new Date(datetime + diff);

	document.getElementById(elementId + '-date').value = datetime.toISOString().slice(0, 10);
	document.getElementById(elementId + '-time').value = datetime.toTimeString().slice(0, 5);
}

function eventStartChange() {
	var newEventStart = getDateTimeInput('event-start');
	diff = newEventStart - eventStart;

	changeDateTimeInput('event-end', diff);
	changeDateTimeInput('log-end', diff);

	eventStart = newEventStart;
}

eventStart = getDateTimeInput('event-start');

document.getElementById('event-start-date').addEventListener('change', eventStartChange);
document.getElementById('event-start-time').addEventListener('change', eventStartChange);

//$('#frm-akceForm-perex').texyla(akce_public);
$('.texyla').texyla(akce);

var elem = document.createElement('input');
elem.setAttribute('type', 'date');

if (elem.type === 'text') {
	$('.date').datepicker();
}

$('#frm-akceForm-description').textareaAutoSize();
$('#frm-akceForm').areYouSure();