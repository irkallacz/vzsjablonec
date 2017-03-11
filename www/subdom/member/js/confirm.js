$(function () {
        $('.confirm').click(function () {
                return confirm(this.getAttribute('data-query'));;
        });
});
