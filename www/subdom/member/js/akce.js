function doTheTrick() {
	$("#deatline").toggle(Boolean($('#frm-akceForm-login_mem').attr('checked')) || Boolean($('#frm-akceForm-login_org').attr('checked')));
}

function copyTime(){
	var timeStart = $("#frm-akceForm-time_start").val();
	var timeEnd = $("#frm-akceForm-time_end").val();
	if (timeStart > timeEnd) $("#frm-akceForm-time_end").val(timeStart);
}

function copyDate() {		
	var dateStart = $("#frm-akceForm-date_start").val();
	var dateEnd = $("#frm-akceForm-date_end").val();
	var dateDeat = $("#frm-akceForm-date_deatline").val();

	if (dateStart > dateEnd) $("#frm-akceForm-date_end").val(dateStart);
	//if (dateStart < dateDeat) $("#frm-akceForm-date_deatline").val(dateStart);
	$("#frm-akceForm-date_deatline").val(dateStart);
}

$(function() {		
	//$('#frm-akceForm-perex').texyla(akce_public);
	$('.texyla').texyla(akce);

	var elem = document.createElement('input');
  elem.setAttribute('type', 'date');

	if ( elem.type === 'text' ) {
		$("#frm-akceForm-date_start").datepicker();
		$("#frm-akceForm-date_end").datepicker();
		$("#frm-akceForm-date_deatline").datepicker();
	}
});