(() => {
  'use strict';
  const stories = window.ARCWELL_STORIES;
  const params = new URLSearchParams(location.search);
  const page = document.body.dataset.page;
  const escape = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const storyUrl = s => `article.html?story=${encodeURIComponent(s.slug)}`;
  const authorUrl = name => `author.html?name=${encodeURIComponent(name)}`;
  const card = s => `<article class="card" data-category="${s.category}"><a class="image-link" href="${storyUrl(s)}"><img src="assets/images/${s.image}.jpg" alt="${s.alt}" width="960" height="640" loading="lazy"></a><a class="eyebrow accent" href="archive.html?category=${s.category}">${s.category} — ${s.section}</a><h3><a href="${storyUrl(s)}">${s.title}</a></h3><div class="meta"><a href="${authorUrl(s.author)}">${s.author}</a><span>·</span><span>${s.time}</span></div></article>`;
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('#main-nav');
  const closeMenu = () => { nav.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); toggle.setAttribute('aria-label','Open navigation'); };
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded',String(open)); toggle.setAttribute('aria-label',open ? 'Close navigation' : 'Open navigation'); nav.classList.toggle('open',open);
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && nav.classList.contains('open')) { closeMenu(); toggle.focus(); } });
  document.addEventListener('click', event => { if (!event.target.closest('.header')) closeMenu(); });
  if (page === 'archive') {
    const requested = params.get('category');
    const category = ['Design','Culture','Nature','Ideas'].includes(requested) ? requested : 'All stories';
    document.querySelectorAll('[data-filter]').forEach(link => { if (link.dataset.filter===category) {link.classList.add('active');link.setAttribute('aria-current','true');} });
    const filtered = category==='All stories' ? stories : stories.filter(s=>s.category===category);
    document.querySelector('#archive-results').innerHTML=filtered.map(card).join('');
    document.querySelector('#archive-count').textContent=`${filtered.length} ${filtered.length===1?'story':'stories'} to explore`;
    if(category!=='All stories') {document.querySelector('#archive-title').textContent=category+'.';document.title=category+' — Arcwell';document.querySelector('#archive-description').textContent={Design:'Spaces and objects that shape the way we live. A closer look at a more considered world.',Culture:'The objects, rituals, and shared stories that give everyday life its texture.',Nature:'Step outside the familiar. Stories about our relationship with the natural world.',Ideas:'New perspectives on the places we share and the futures we imagine.'}[category];}
  }
  if(page==='search') {
    const input=document.querySelector('#query');
    const query=(params.get('q')||'').trim().slice(0,150);
    input.value=query;
    if(params.has('q')) {
      const matched=query ? stories.filter(s=>[s.title,s.description,s.category,s.section,s.author].join(' ').toLowerCase().includes(query.toLowerCase())) : stories;
      document.querySelector('#search-count').textContent=query ? `${matched.length} ${matched.length===1?'story':'stories'} for “${query}”` : 'All stories in the journal';
      document.querySelector('#search-results').innerHTML=matched.length ? matched.map(card).join('') : `<div class="empty"><h2>A different word might open a door.</h2><p>No stories found for “${escape(query)}”. Try design, nature, or a contributor’s name.</p><a class="button" href="search.html?q=">Browse all stories ↗</a></div>`;
    }
  }
  const bios = {
    'Alex Morgan':'A writer exploring the relationship between the places we inhabit and the lives we lead. Interested in thoughtful design, quiet rituals, and the occasional long walk.',
    'Jamie Chen':'Writing about culture, meaningful objects, and the small adventures that make everyday life feel a little bigger.',
    'Sam Rivera':'Exploring cities, shared spaces, and the ideas that help us imagine a more welcoming future.'
  };
  if(page==='author') {
    const name=Object.hasOwn(bios,params.get('name')) ? params.get('name') : 'Alex Morgan';
    document.title=name+' — Arcwell';
    document.querySelector('#author-name').textContent=name;
    document.querySelector('#author-bio').textContent=bios[name];
    document.querySelector('#author-initials').textContent=name.split(' ').map(x=>x[0]).join('');
    document.querySelector('.section-head h2').textContent='Stories by '+name.split(' ')[0];
    document.querySelector('#author-stories').innerHTML=stories.filter(s=>s.author===name).map(card).join('');
  }
  if(page==='article') {
    const slug=params.get('story');
    const story=slug ? stories.find(s=>s.slug===slug) : stories[0];
    if(!story) {location.replace('404.html');return;}
    document.title=story.title+' — Arcwell';
    document.querySelector('#article-title').textContent=story.title;
    document.querySelector('#article-description').textContent=story.description;
    document.querySelector('#article-category').textContent=story.category+' / '+story.section;
    document.querySelector('#article-category').href='archive.html?category='+story.category;
    document.querySelector('#article-date').textContent=story.date;
    document.querySelector('#article-meta').innerHTML=`<div class="meta"><a href="${authorUrl(story.author)}">${story.author}</a><span>·</span><span>${story.time}</span></div>`;
    document.querySelector('#article-photo img').src=`assets/images/${story.image}.jpg`;
    document.querySelector('#article-photo img').alt=story.alt;
    const photographers={architecture:'Matt Bango',chair:'Matt Bango',forest:'Redd Angelo',building:'Tom Hill',landscape:'Nathan Anderson',interior:'Matt Bango'};
    document.querySelector('#article-caption').textContent='Photograph by '+photographers[story.image]+' / StockSnap';
    document.querySelector('#byline-name').textContent=story.author;
    document.querySelector('#byline-name').href=authorUrl(story.author);
    document.querySelector('#byline-initials').textContent=story.author.split(' ').map(x=>x[0]).join('');
    document.querySelector('#byline-bio').textContent=bios[story.author];
    const essays={
      'less-but-better':[
        'The things that stay','Some objects earn their place slowly. A chair becomes the place we read on Sunday mornings. A cup becomes part of the rhythm of a day. Their value is built through use, one ordinary moment at a time.',
        'Choosing with intention','Choosing less is not a competition in restraint. It is a way to ask better questions of the objects we bring into our lives. Will this be useful? Can it be repaired? Does it invite us to enjoy the time we spend with it?',
        'The best objects leave room for a life to happen around them.',
        'A lasting relationship','The familiar chair does not need to be replaced because the room has changed. Sometimes its familiar shape is exactly what a new space needs. Keeping an object can be its own small act of imagination.'
      ],
      'forest-listening':[
        'Learning a different pace','At first, a forest can seem almost silent. Then a branch shifts, a bird answers another bird, and the wind begins to separate into a hundred small sounds. The world has been speaking all along. We have only just arrived.',
        'Listening without an agenda','We are used to directing our attention toward a task. Outside, it can move more freely. Follow a line of moss along a stone. Watch the light pass between branches. Nothing has to become a photograph or a useful lesson.',
        'Sometimes the most interesting thing we can do is stay still.',
        'Taking the quiet home','We cannot carry a forest with us, but we can remember how it felt to listen. An open window, a short walk, or a moment beside a tree can make a small opening in a crowded day.'
      ],
      'cities-human-scale':[
        'A city at walking speed','A city feels different when we move through it on foot. A broad avenue becomes a sequence of thresholds: a bench, a shop window, a stretch of shade. What looked simple on a map becomes a place with texture.',
        'The space between buildings','Architecture helps shape a city, but much of public life happens in the gaps. A comfortable place to sit can turn a route into a destination. A crossing that feels easy can connect two neighborhoods more meaningfully than a landmark.',
        'A welcoming city makes room for people to linger.',
        'Starting with the ordinary','Thinking at a human scale begins with observing everyday routines. Who waits here? Who finds this route difficult? Small changes, considered carefully, can make shared places feel more generous.'
      ],
      'taking-long-way':[
        'Beyond the quickest route','The quickest way home is familiar enough to disappear. We stop noticing the corners we turn and the windows we pass. A small detour can bring the journey back into focus.',
        'A little room to wander','Taking the long way does not have to mean going far. It can mean following a path along a garden, stopping beside an unfamiliar doorway, or choosing the street with the trees. The point is to leave room for something unplanned.',
        'A detour can be a way of arriving more fully.',
        'The journey as a place','When every journey is only a means to an end, the space between destinations becomes invisible. Walking a little more slowly gives that space a chance to become part of the day.'
      ],
      'objects-with-stories':[
        'The marks of a life','A worn armrest can tell us something a pristine surface cannot. It speaks of repetition, comfort, and the many times someone settled into a familiar place at the end of a day.',
        'Keeping the imperfect','Age changes an object, but change is not always a loss. A repaired seam or a softened edge can make something feel more particular. It becomes this chair, in this room, carrying this history.',
        'The things we keep can become a quiet record of who we are.',
        'Making room for another chapter','An older object can find a new life without losing what makes it distinctive. We can care for it, repair it, and let it belong to a different arrangement. Its story does not have to end when our tastes change.'
      ]
    };
    if(essays[story.slug]) {
      const e=essays[story.slug];
      document.querySelector('#article-body').innerHTML=`<p class="lead">${e[1]}</p><h2 id="room">${e[0]}</h2><p>${story.description}</p><p>There is something useful in giving a familiar subject a little more time. Details emerge, assumptions loosen, and an ordinary experience begins to feel more specific.</p><h2 id="everyday">${e[2]}</h2><p>${e[3]}</p><blockquote>“${e[4]}”</blockquote><h2 id="attention">${e[5]}</h2><p>${e[6]}</p><p>Perhaps that is enough for today: to notice one thing we might otherwise have passed by, and let it change the shape of a familiar moment.</p>`;
      document.querySelector('#contents').innerHTML=`<a href="#room">${e[0]}</a><a href="#everyday">${e[2]}</a><a href="#attention">${e[5]}</a>`;
    }
    document.querySelector('#share-article').addEventListener('click',async()=>{
      const status=document.querySelector('#share-status');
      try{await navigator.clipboard.writeText(location.href);status.textContent='Story link copied.';}
      catch{status.textContent='Copy the address from your browser to share this story.';}
    });
  }
  document.querySelector('#embed-preview')?.addEventListener('click',()=>{document.querySelector('#embed-status').textContent='Preview state: the approved media player would load here. No external media is loaded in this prototype.';});
})();
