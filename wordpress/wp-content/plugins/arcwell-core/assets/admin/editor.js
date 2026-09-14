(function (wp) {
    'use strict';
    const el = wp.element.createElement;
    const {useState} = wp.element;
    const {useSelect, useDispatch} = wp.data;
    const {TextControl, TextareaControl, SelectControl, Button, Notice, Spinner} = wp.components;
    const Panel = wp.editor.PluginDocumentSettingPanel || wp.editPost.PluginDocumentSettingPanel;
    const bases = {post: 'posts', page: 'pages', arcwell_series: 'arcwell_series', arcwell_topic: 'arcwell_topic'};
    const text = item => item?.title?.raw || item?.name || item?.slug || 'Loading selection…';
    function PickedItem({id, type, index, count, onRemove, onMove}) {
        const item = useSelect(select => select('core').getEntityRecord(type === 'arcwell_topic' ? 'taxonomy' : 'postType', type, id), [id, type]);
        return el('li', {className: 'arcwell-picked'}, el('span', {}, text(item)),
            count > 1 && el(Button, {size: 'small', disabled: index === 0, onClick: () => onMove(-1), 'aria-label': 'Move selection up'}, '↑'),
            count > 1 && el(Button, {size: 'small', disabled: index === count - 1, onClick: () => onMove(1), 'aria-label': 'Move selection down'}, '↓'),
            el(Button, {isDestructive: true, size: 'small', onClick: onRemove, 'aria-label': 'Remove selection'}, 'Remove'));
    }
    function Picker({label, type = 'post', ids = [], limit = 1, onChange}) {
        const [search, setSearch] = useState('');
        const results = useSelect(select => select('core').getEntityRecords(type === 'arcwell_topic' ? 'taxonomy' : 'postType', type,
            {search, per_page: 20, context: 'edit', ...(type === 'arcwell_topic' ? {hide_empty: false} : {status: 'any'})}), [type, search]);
        function move(index, by) { const next = ids.slice(); [next[index], next[index + by]] = [next[index + by], next[index]]; onChange(next); }
        return el('div', {className: 'arcwell-picker'}, el('strong', {}, label),
            el('ol', {}, ids.map((id, index) => el(PickedItem, {key: id, id, type, index, count: ids.length,
                onRemove: () => onChange(ids.filter(value => value !== id)), onMove: by => move(index, by)}))),
            ids.length < limit && el(TextControl, {label: 'Find ' + label.toLowerCase(), value: search, onChange: setSearch}),
            ids.length < limit && (!results ? el(Spinner) : el(SelectControl, {label: 'Add selection', value: '',
                options: [{label: 'Choose an item…', value: ''}, ...results.filter(item => !ids.includes(item.id)).map(item => ({label: text(item), value: item.id}))],
                onChange: value => {if (value) onChange([...ids, Number(value)]);}})),
            el('small', {}, ids.length + ' of ' + limit + ' selected. Unpublished selections stay hidden from public visitors.'));
    }
    function Values({values, onChange}) {
        return el('div', {}, el('strong', {}, 'About value statements'), values.map((value, index) => el('div', {key: index, className: 'arcwell-value'},
            el(TextControl, {label: 'Heading', maxLength: 80, value: value.heading || '', onChange: heading => onChange(values.map((item, n) => n === index ? {...item, heading} : item))}),
            el(TextareaControl, {label: 'Body', maxLength: 500, value: value.body || '', onChange: body => onChange(values.map((item, n) => n === index ? {...item, body} : item))}),
            el(Button, {isDestructive: true, onClick: () => onChange(values.filter((_, n) => n !== index))}, 'Remove statement'))),
            values.length < 3 && el(Button, {variant: 'secondary', onClick: () => onChange([...values, {heading: '', body: ''}])}, 'Add statement'));
    }
    function PreviewControl({id, type}) {
        const [busy, setBusy] = useState(false);
        const [error, setError] = useState('');
        const [revision, setRevision] = useState('');
        const records = useSelect(select => select('core').getRevisions('postType', type, id, {per_page: 20}), [type, id]);
        async function launch() {
            const win = window.open('about:blank', '_blank');
            if (win) win.opener = null;
            setBusy(true); setError('');
            try {
                if (!revision) await wp.data.dispatch('core/editor').autosave();
                const image = Number(wp.data.select('core/editor').getEditedPostAttribute('featured_media') || 0);
                const query = new URLSearchParams({context: 'edit', _fields: 'arcwell_preview'});
                if (revision) query.set('arcwell_revision', revision);
                else query.set('arcwell_featured_image', String(image));
                const response = await wp.apiFetch({path: '/wp/v2/' + bases[type] + '/' + id + '?' + query});
                if (!response.arcwell_preview) throw new Error('Preview is unavailable. Check Arcwell configuration and your editing permissions.');
                if (win) win.location.href = response.arcwell_preview;
                else throw new Error('Allow pop-ups for this editor, then try preview again.');
            } catch (failure) { if (win) win.close(); setError(failure.message || 'Unable to open preview.'); }
            finally { setBusy(false); }
        }
        return el('div', {}, el('p', {}, 'Preview the saved draft or latest autosave. Taxonomy, profile and menu changes are saved immediately.'),
            el(SelectControl, {label: 'Preview version', value: revision, options: [{label: 'Current edits (autosave first)', value: ''},
                ...(records || []).map(record => ({label: 'Saved revision · ' + record.date, value: String(record.id)}))], onChange: setRevision}),
            el(Button, {variant: 'primary', disabled: busy || !arcwellEditor.previewReady, onClick: launch}, busy ? 'Preparing preview…' : 'Preview in Arcwell'),
            !arcwellEditor.previewReady && el('p', {}, 'An administrator must configure Arcwell preview first.'),
            error && el(Notice, {status: 'error', isDismissible: false}, error));
    }
    function Settings() {
        const {id, type, meta} = useSelect(select => {
            const editor = select('core/editor');
            return {id: editor.getCurrentPostId(), type: editor.getCurrentPostType(), meta: editor.getEditedPostAttribute('meta') || {}};
        }, []);
        const {editPost} = useDispatch('core/editor');
        const setMeta = (key, value) => editPost({meta: {...meta, [key]: value}});
        const home = meta._arcwell_homepage || {};
        const setHome = (key, value) => setMeta('_arcwell_homepage', {...home, [key]: value});
        return el(wp.element.Fragment, {},
            el(Panel, {name: 'arcwell-preview', title: 'Arcwell preview'}, el(PreviewControl, {id, type})),
            type === 'page' && Number(id) === Number(arcwellEditor.frontPage) && el(Panel, {name: 'arcwell-home', title: 'Arcwell homepage'},
                ...Object.entries({heading: ['Heading', 100], emphasis: ['Emphasized heading', 100], intro: ['Introduction', 400], heroOverlay: ['Cover photo overlay', 120], manifestoHeading: ['Manifesto heading', 180], manifestoEmphasis: ['Manifesto emphasis', 180]}).map(([key, [label, maxLength]]) =>
                    el(TextareaControl, {key, label, maxLength, value: home[key] || '', onChange: value => setHome(key, value)})),
                ...[['heroPostId', 'Cover story', 'post'], ['featuredTopicId', 'Featured Topic', 'arcwell_topic'], ['featuredSeriesId', 'Featured Series', 'arcwell_series'], ['aboutPageId', 'About page', 'page']].map(([key, label, entityType]) =>
                    el(Picker, {key, label, type: entityType, ids: home[key] ? [home[key]] : [], onChange: ids => setHome(key, ids[0] || 0)})),
                el(Picker, {label: "Editor's picks", ids: home.editorsPickIds || [], limit: 3, onChange: ids => setHome('editorsPickIds', ids)})),
            type === 'page' && el(Panel, {name: 'arcwell-page', title: 'Arcwell page presentation'},
                el(SelectControl, {label: 'Template', value: meta._arcwell_page_template || 'standard', options: [{label: 'Standard', value: 'standard'}, {label: 'About', value: 'about'}], onChange: value => setMeta('_arcwell_page_template', value)}),
                meta._arcwell_page_template === 'about' && el(Values, {values: meta._arcwell_about_values || [], onChange: values => setMeta('_arcwell_about_values', values)})),
            type === 'arcwell_series' && el(Panel, {name: 'arcwell-series', title: 'Arcwell series'},
                el(TextControl, {label: 'Artwork label', maxLength: 24, value: meta._arcwell_art_label || '', onChange: value => setMeta('_arcwell_art_label', value)}),
                el(TextControl, {label: 'Edition label', maxLength: 40, value: meta._arcwell_edition_label || '', onChange: value => setMeta('_arcwell_edition_label', value)}),
                el(Picker, {label: 'Ordered articles', ids: meta._arcwell_post_ids || [], limit: 100, onChange: ids => setMeta('_arcwell_post_ids', ids)})));
    }
    // Native Gutenberg autosave preview links must include the current image, including removal (0).
    wp.apiFetch.use((options, next) => {
        if (/\/autosaves(?:\?|$)/.test(options.path || '') && String(options.method || '').toUpperCase() === 'POST') {
            options = {...options, data: {...options.data, arcwell_featured_image: Number(wp.data.select('core/editor').getEditedPostAttribute('featured_media') || 0)}};
        }
        return next(options);
    });
    wp.plugins.registerPlugin('arcwell-editor', {render: Settings, icon: 'admin-site-alt3'});
})(window.wp);
