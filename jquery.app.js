(function ($, window) {
    $(document).ready(function () {

        $('.js-select-profile').change(function () {
            $('#js-profile-form').submit();
        });

        $('#js-profile-add').click(function () {
            var name = prompt('Zadejte název nového profilu:');

            if (name) {
                ajaxProfile({
                    action: 'add-profile',
                    profile: name,
                })
                    .done(handleResponse);
            }
        });

        $('#js-profile-rename').click(function () {
            var newName = prompt('Zadejte nový název profilu:');

            if (newName) {
                var profileId = $(this).data('profile-id');

                ajaxProfile({
                    action: 'rename-profile',
                    profile: profileId,
                    name: newName,
                })
                    .done(handleResponse);
            }
        });

        $('#js-profile-remove').click(function() {
            if (confirm('Opravdu chcete profil odstranit?')) {
                var profileId = $(this).data('profile-id');

                ajaxProfile({
                    action: 'remove-profile',
                    profile: profileId,
                })
                    .done(handleResponse);
            }
        });

        function ajaxProfile(data) {
            return $.ajax({
                method: 'POST',
                url: getLocation(),
                data: data,
                dataType: 'json',
            });
        }

        function getLocation() {
            var href = window.location.href;

            return href.split('?')[0];
        }

        function handleResponse(response) {
            if (response && response.status === 'ok') {
                redirectToProfile(response.id);
            }
        }

        function redirectToProfile(id) {
            window.location.href = getLocation() + '?profile=' + id;
        }

    });
})(jQuery, window);
