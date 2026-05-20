// assets/js/main.js
document.addEventListener('DOMContentLoaded', function(){
  // Add subtle number animation for cards with amounts
  const numEls = document.querySelectorAll('.card p');
  numEls.forEach(el=>{
    const txt = el.textContent.replace(/[^0-9\.\-]/g,'');
    if(!txt) return;
    const val = parseFloat(txt);
    if(isNaN(val)) return;
    // simple count animation
    let start = 0;
    const duration = 800;
    const stepTime = 20;
    const steps = Math.ceil(duration / stepTime);
    let current = 0;
    const inc = val / steps;
    const formatter = new Intl.NumberFormat('es-CO',{style:'currency', currency:'COP', minimumFractionDigits:2, maximumFractionDigits:2});
    const timer = setInterval(()=>{
      current += inc;
      if(current >= val) {
        el.textContent = formatter.format(val);
        clearInterval(timer);
      } else {
        el.textContent = formatter.format(current);
      }
    }, stepTime);
  });

  const closeAll = () => {
    document.querySelectorAll('.dropdown.open, .user-menu.open').forEach(el => el.classList.remove('open'));
  };

  document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const dropdown = toggle.closest('.dropdown');
      if(!dropdown) return;
      const isOpen = dropdown.classList.contains('open');
      closeAll();
      if(!isOpen) dropdown.classList.add('open');
    });
  });

  document.querySelectorAll('.user-menu > span').forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const menu = trigger.closest('.user-menu');
      if(!menu) return;
      const isOpen = menu.classList.contains('open');
      closeAll();
      if(!isOpen) menu.classList.add('open');
    });
  });

  document.addEventListener('click', closeAll);
  document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') closeAll();
  });

  // Dark Mode Logic
  const themeToggle = document.createElement('button');
  themeToggle.className = 'theme-toggle';
  themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
  themeToggle.setAttribute('title', 'Cambiar tema');
  
  const navLinks = document.querySelector('.nav-links');
  if(navLinks){
      const li = document.createElement('li');
      li.appendChild(themeToggle);
      navLinks.appendChild(li);
  }

  const currentTheme = localStorage.getItem('theme') || 'light';
  if (currentTheme === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
  }

  themeToggle.addEventListener('click', () => {
    let theme = document.documentElement.getAttribute('data-theme');
    if (theme === 'dark') {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('theme', 'light');
      themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('theme', 'dark');
      themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    }
  });
});
