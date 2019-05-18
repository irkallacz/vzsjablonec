function doTheTrick() {
	$('#deatline').toggle(Boolean($('#frm-akceForm-login_mem').attr('checked')) || Boolean($('#frm-akceForm-login_org').attr('checked')));
}

function str2date(str) {
	return new Date(str);
}

function copyDate() {		
	var date = document.getElementById('frm-akceForm-date_start').value;
    //var time = document.querySelector('#frm-akceForm-date_start + input').value;

    var dateStart = str2date(date);

	var end = document.getElementById('frm-akceForm-date_end').value;
    var dateEnd = str2date(end);

	if (dateStart > dateEnd) document.getElementById('frm-akceForm-date_end').value = date;
    document.getElementById('frm-akceForm-date_deatline').value = date;
}

$(function() {		
	//$('#frm-akceForm-perex').texyla(akce_public);
	$('.texyla').texyla(akce);

	var elem = document.createElement('input');
  	elem.setAttribute('type', 'date');

	if (elem.type === 'text') {
	     $('.date').datepicker();
	}

    $('#frm-akceForm-date_start').change(function(){copyDate()});
	$('#frm-akceForm-date_end').change(function(){copyDate()});
    //$('#frm-akceForm-date_deatline').change(function(){copyDate()});
});