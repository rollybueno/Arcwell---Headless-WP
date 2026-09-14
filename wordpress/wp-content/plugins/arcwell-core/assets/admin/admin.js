(function () {
    'use strict';
    const status = document.querySelector('#arcwell-status');
    const container = document.querySelector('#arcwell-deliveries');
    function message(value) { if (status) status.textContent = value; }
    async function refresh() {
        if (!container) return;
        try {
            const data = await wp.apiFetch({path: '/arcwell/v1/diagnostics'});
            container.replaceChildren();
            const table = document.createElement('table'); table.className = 'widefat striped';
            const head = table.createTHead().insertRow();
            ['Event', 'State', 'Attempts', 'HTTP', 'Result', 'Action'].forEach(title => {const th = document.createElement('th'); th.textContent = title; head.append(th);});
            const body = table.createTBody();
            for (const item of data.recent) {
                const row = body.insertRow();
                [item.event_id, item.state, item.attempts, item.http_status, item.error_code || '—'].forEach(value => {row.insertCell().textContent = value;});
                const action = row.insertCell();
                if (item.state === 'failed') {
                    const button = document.createElement('button'); button.className = 'button'; button.textContent = 'Retry';
                    button.onclick = async () => {button.disabled = true; try {await wp.apiFetch({path: '/arcwell/v1/webhooks/' + item.event_id + '/retry', method: 'POST'}); message('Retry queued.'); await refresh();} catch (error) {message(error.message);button.disabled=false;}};
                    action.append(button);
                }
            }
            container.append(table);
            if (data.persistenceError) message('Publishing event persistence needs attention: ' + data.persistenceError);
        } catch (error) { message(error.message || 'Unable to load diagnostics.'); }
    }
    document.querySelector('#arcwell-refresh')?.addEventListener('click', refresh);
    document.querySelector('#arcwell-test')?.addEventListener('click', async event => {
        event.target.disabled = true;
        try {const result = await wp.apiFetch({path: '/arcwell/v1/webhooks/test', method: 'POST'});message('Test queued: ' + result.eventId + '. Refresh after the worker runs to see delivery status.');await refresh();}
        catch (error) {message(error.message || 'Unable to queue test.');}
        finally {event.target.disabled = false;}
    });
    document.querySelectorAll('.arcwell-select-media').forEach(button => button.addEventListener('click', () => {
        const frame = wp.media({title: 'Choose a topic image', library: {type: 'image'}, multiple: false});
        frame.on('select', () => {const image = frame.state().get('selection').first().toJSON();const box = button.closest('.arcwell-media');box.querySelector('input').value=image.id;box.querySelector('.arcwell-media-label').textContent=image.title;});frame.open();
    }));
    document.querySelectorAll('.arcwell-clear-media').forEach(button => button.addEventListener('click', () => {const box=button.closest('.arcwell-media');box.querySelector('input').value='0';box.querySelector('.arcwell-media-label').textContent='No image selected';}));
    refresh();
})();
