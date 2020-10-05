function doTheTrick() {
	$('#deatline').toggle(Boolean($('#frm-akceForm-login_mem').attr('checked')) || Boolean($('#frm-akceForm-login_org').attr('checked')));
}

function parseDate(elementName) {
	return new Date(document.getElementById(elementName + '-date').value + 'T' + document.getElementById(elementName + '-time').value);
}

function setDateTime(elementName, datetime) {
	document.getElementById(elementName + '-date').value = datetime.toISOString().slice(0, 10);
	document.getElementById(elementName + '-time').value = datetime.toTimeString().slice(0, 5);
}

function eventEndChange() {
	eventEnd = parseDate('event-end');
}

function logEndChange() {
	logEnd = parseDate('log-end');
}

function eventStartChange() {
	var newEventStart = parseDate('event-start');
	diff = newEventStart - eventStart;
	logEnd = new Date(logEnd.getTime() + diff);
	eventEnd = new Date(eventEnd.getTime() + diff);

	setDateTime('log-end', logEnd);
	setDateTime('event-end', eventEnd);

	eventStart = newEventStart;
}

eventStart = parseDate('event-start');
eventEnd = parseDate('event-end');
logEnd = parseDate('log-end');

document.getElementById('event-start-date').addEventListener('change', eventStartChange);
document.getElementById('event-start-time').addEventListener('change', eventStartChange);

document.getElementById('event-end-date').addEventListener('change', eventEndChange);
document.getElementById('event-end-time').addEventListener('change', eventEndChange);

document.getElementById('log-end-date').addEventListener('change', logEndChange);
document.getElementById('log-end-time').addEventListener('change', logEndChange);

//$('#frm-akceForm-perex').texyla(akce_public);
$('.texyla').texyla(akce);

var elem = document.createElement('input');
elem.setAttribute('type', 'date');

if (elem.type === 'text') {
	$('.date').datepicker();
}

$('#frm-akceForm-description').textareaAutoSize();
$('#frm-akceForm').areYouSure();