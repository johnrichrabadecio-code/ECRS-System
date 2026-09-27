document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('[data-search]').forEach(input=>input.addEventListener('input',()=>{const q=input.value.toLowerCase();document.querySelectorAll(input.dataset.search).forEach(el=>el.style.display=el.innerText.toLowerCase().includes(q)?'':'none')}));

// Clickable notification centers (student + admin). Read state is kept locally in this browser.
document.querySelectorAll('[data-notification-center]').forEach(center=>{
  const toggle=center.querySelector('.notification-toggle');
  const close=center.querySelector('.notification-close');
  const markRead=center.querySelector('.notification-read');
  const key='ecrs-notifications-read-'+(center.dataset.notificationKey||'default');

  const setRead=()=>{
    center.classList.add('is-read');
    try{localStorage.setItem(key,'1')}catch(e){}
  };
  try{if(localStorage.getItem(key)==='1')center.classList.add('is-read')}catch(e){}

  const open=()=>{
    document.querySelectorAll('[data-notification-center].open').forEach(other=>{if(other!==center){other.classList.remove('open');const b=other.querySelector('.notification-toggle');if(b)b.setAttribute('aria-expanded','false')}});
    center.classList.add('open');
    toggle.setAttribute('aria-expanded','true');
  };
  const shut=()=>{
    center.classList.remove('open');
    toggle.setAttribute('aria-expanded','false');
  };

  toggle?.addEventListener('click',e=>{
    e.stopPropagation();
    center.classList.contains('open')?shut():open();
  });
  close?.addEventListener('click',e=>{e.preventDefault();shut()});
  markRead?.addEventListener('click',e=>{e.preventDefault();setRead()});
  center.querySelectorAll('.notification-item').forEach(item=>item.addEventListener('click',setRead));
});

document.addEventListener('click',e=>{
  document.querySelectorAll('[data-notification-center].open').forEach(center=>{
    if(!center.contains(e.target)){
      center.classList.remove('open');
      const toggle=center.querySelector('.notification-toggle');
      if(toggle)toggle.setAttribute('aria-expanded','false');
    }
  });
});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('[data-notification-center].open').forEach(center=>{center.classList.remove('open');const toggle=center.querySelector('.notification-toggle');if(toggle)toggle.setAttribute('aria-expanded','false')})}});
