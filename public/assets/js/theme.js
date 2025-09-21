// E2CE theme toggle: keeps choice in localStorage, defaults to system
(function(){
    const STORAGE_KEY = 'e2-theme';
    const root = document.documentElement;
  
    function apply(theme){
      if(theme === 'dark'){ root.setAttribute('data-theme','dark'); }
      else { root.removeAttribute('data-theme'); }
    }
    function current(){
      const saved = localStorage.getItem(STORAGE_KEY);
      if(saved) return saved;
      const media = window.matchMedia('(prefers-color-scheme: dark)');
      return media.matches ? 'dark' : 'light';
    }
  
    // init
    apply(current());
  
    // optional: attach to a button with id="theme-toggle"
    window.addEventListener('DOMContentLoaded', ()=>{
      const btn = document.getElementById('theme-toggle');
      if(!btn) return;
      btn.addEventListener('click', ()=>{
        const next = (current() === 'dark') ? 'light' : 'dark';
        localStorage.setItem(STORAGE_KEY, next);
        apply(next);
      });
    });
  })();
  