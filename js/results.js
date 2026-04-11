/**
 * JavaScript for result lists.
 *
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

function table_sortCallback(tableId, column, sortDown)
{
    HordeCore.doAction('setPrefValue', { pref: 'sortby', value: column });
    HordeCore.doAction('setPrefValue', { pref: 'sortdir', value: sortDown });
}

document.addEventListener('DOMContentLoaded', function() {
    var checkAll = document.getElementById('check-all');
    if (checkAll) {
        checkAll.addEventListener('click', function() {
            var inputs = Array.from(
                document.getElementById('delete-form').querySelectorAll('input[type="checkbox"][name="ticket[]"]')
            );
            var check = inputs.some(function(c) { return !c.checked; });
            inputs.forEach(function(input) {
                input.checked = check;
            });
            checkAll.checked = check;
        });
    }
});
