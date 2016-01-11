(function ($, window) {
    $(document).ready(function () {

        $('.js-select-profile').change(function () {
            $('#js-profile-form').submit();
        });

        $('#js-profile-add').click(function () {
            var name = prompt('Zadejte název nového profilu:');

            if (name) {
                var location = getLocation();

                $.ajax({
                    method: 'POST',
                    url: location,
                    data: {
                        action: 'add-profile',
                        profile: name,
                    },
                    dataType: 'json',
                }).done(function (response) {
                    if (response && response.status === 'ok') {
                        window.location.href = location + '?profile=' + response.id;
                    }
                });
            }
        });

        function getLocation() {
            var href = window.location.href;

            return href.split('?')[0];
        }

    });
})(jQuery, window);
