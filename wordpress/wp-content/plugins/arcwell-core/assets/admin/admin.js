(function () {
    'use strict';
    const status = document.querySelector('#arcwell-status');
    const container = document.querySelector('#arcwell-deliveries');
    function message(value) { if (status) status.textContent = value; }
    async function refresh() {
        if (!container) return;
        container.setAttribute('aria-busy', 'true');
        try {
            const data = await wp.apiFetch({path: '/arcwell/v1/diagnostics'});
            container.replaceChildren();
            const worker = document.querySelector('#arcwell-worker');
            if (worker) worker.textContent = 'Worker last run: ' + (data.workerLastRun ? new Date(data.workerLastRun * 1000).toISOString().replace('T', ' ').replace('.000Z', ' UTC') : 'Not yet run — configure the scheduler above.');
            if (!data.recent.length) {
                const empty = document.createElement('div'); empty.className = 'arcwell-empty';
                const title = document.createElement('strong'); title.textContent = 'No publishing activity yet';
                const help = document.createElement('p'); help.textContent = 'Publishing events and connection tests appear here once queued. Delivery runs through the scheduled worker.';
                empty.append(title, help); container.append(empty);
                if (data.persistenceError) message('Publishing event persistence needs attention: ' + data.persistenceError);
                return;
            }
            const table = document.createElement('table'); table.className = 'widefat striped';
            const head = table.createTHead().insertRow();
            ['Event', 'State', 'Attempts', 'HTTP', 'Result', 'Action'].forEach(title => {const th = document.createElement('th'); th.scope = 'col'; th.textContent = title; head.append(th);});
            const body = table.createTBody();
            for (const item of data.recent) {
                const row = body.insertRow();
                [item.event_id, item.state, item.attempts, item.http_status, item.error_code || '—'].forEach(value => {row.insertCell().textContent = value;});
                row.cells[0].className = 'arcwell-event-id';
                const badge = document.createElement('span'); badge.className = 'arcwell-badge ' + (item.state === 'sent' ? 'is-ready' : 'is-pending'); badge.textContent = item.state;
                row.cells[1].replaceChildren(badge);
                if (!Number(item.http_status)) row.cells[3].textContent = '—';
                const action = row.insertCell(); action.textContent = '—';
                if (item.state === 'failed') {
                    const button = document.createElement('button'); button.className = 'button'; button.textContent = 'Retry';
                    button.onclick = async () => {button.disabled = true; try {await wp.apiFetch({path: '/arcwell/v1/webhooks/' + item.event_id + '/retry', method: 'POST'}); message('Retry queued.'); await refresh();} catch (error) {message(error.message);button.disabled=false;}};
                    action.replaceChildren(button);
                }
            }
            container.append(table);
            if (data.persistenceError) message('Publishing event persistence needs attention: ' + data.persistenceError);
        } catch (error) { message(error.message || 'Unable to load diagnostics.'); if (!container.querySelector('table')) container.textContent = 'Publishing activity could not be loaded. Use Refresh to try again.'; }
        finally { container.setAttribute('aria-busy', 'false'); }
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
    const setup = document.querySelector('#arcwell-connection-form');
    const feedback = document.querySelector('#arcwell-setup-feedback');
    const setupMessage = text => { if (feedback) feedback.textContent = text; };
    async function keyValue(input) {
        if (input.value) return input.value;
        if (input.closest('.arcwell-connection-field').dataset.saved !== 'yes') throw new Error('Generate a key first.');
        const response = await fetch(setup.dataset.ajax, {method:'POST', credentials:'same-origin', cache:'no-store', body:new URLSearchParams({action:'arcwell_reveal_key', nonce:setup.dataset.nonce, key:input.id})});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data?.message || 'Unable to retrieve the saved key. Reload the page and try again.');
        return result.data.value;
    }
    setup?.querySelectorAll('.arcwell-generate').forEach(button => button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        if ((input.closest('.arcwell-connection-field').dataset.saved === 'yes' || input.value) && !window.confirm('Replace this value? After saving, update the matching value on your frontend website too.')) return;
        try {
            if (!window.crypto?.getRandomValues) throw new Error('Secure generation is unavailable in this browser. Try an up-to-date browser.');
            const bytes = window.crypto.getRandomValues(new Uint8Array(32));
            const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
            input.value = input.id === 'ARCWELL_SOURCE_ID' ? 'arcwell-' + hex.slice(0, 24) : hex;
            input.dispatchEvent(new Event('input', {bubbles:true}));
            setupMessage('Generated. Copy the value to your frontend setup, then save connection settings.');
        } catch (error) { setupMessage(error.message); }
    }));
    setup?.querySelectorAll('.arcwell-show, .arcwell-copy').forEach(button => button.addEventListener('click', async () => {
        const input = document.getElementById(button.dataset.target);
        button.disabled = true;
        try {
            if (button.classList.contains('arcwell-show')) {
                if (input.type === 'password') { input.value = await keyValue(input); input.type = 'text'; button.textContent = 'Hide key'; button.setAttribute('aria-pressed','true'); }
                else { input.type = 'password'; button.textContent = 'Show key'; button.setAttribute('aria-pressed','false'); }
            } else {
                const value = input.id === 'ARCWELL_SOURCE_ID' ? input.value : await keyValue(input);
                if (!value) throw new Error('Generate a value first.');
                if (!navigator.clipboard?.writeText) throw new Error('Clipboard access is unavailable. Use Show key, then select and copy the value manually.');
                await navigator.clipboard.writeText(value); setupMessage('Copied. Paste this into the matching setting on your frontend website.');
            }
        } catch (error) { setupMessage(error.message || 'Unable to complete this action.'); }
        finally { button.disabled = false; }
    }));

    refresh();
})();
