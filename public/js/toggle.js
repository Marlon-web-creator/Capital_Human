document.addEventListener("DOMContentLoaded", function () {
  const sidebarToggle = document.getElementById("sidebar-toggle");
  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", function () {
      document
        .querySelector(".admin-container")
        .classList.toggle("sidebar-collapsed");
    });
  }

  const themeToggle = document.getElementById("theme-toggle");
  const themeIcon = document.getElementById("theme-icon");
  const logoImg = document.getElementById("logo-img");
  const footerLogo = document.getElementById("footer-logo");
  
  if (themeToggle) {
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    if (savedTheme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
      if (themeIcon) themeIcon.className = 'bi bi-sun-fill';
      if (logoImg) logoImg.src = '/Capital_HumanMVC/public/images/logo-blanco.png';
      if (footerLogo) footerLogo.src = '/Capital_HumanMVC/public/images/logo-blanco.png';
    } else {
      document.documentElement.setAttribute('data-theme', 'light');
      if (themeIcon) themeIcon.className = 'bi bi-moon-fill';
      if (logoImg) logoImg.src = 'public/images/logo-negro.png';
      if (footerLogo) footerLogo.src = 'public/images/logo-negro.png';
    }

    themeToggle.addEventListener("click", function () {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      
      if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'light');
        if (themeIcon) themeIcon.className = 'bi bi-moon-fill';
        if (logoImg) logoImg.src = 'public/images/logo-negro.png';
        if (footerLogo) footerLogo.src = 'public/images/logo-negro.png';
        localStorage.setItem('theme', 'light');
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        if (themeIcon) themeIcon.className = 'bi bi-sun-fill';
        if (logoImg) logoImg.src = '/Capital_HumanMVC/public/images/logo-blanco.png';
        if (footerLogo) footerLogo.src = '/Capital_HumanMVC/public/images/logo-blanco.png';
        localStorage.setItem('theme', 'dark');
      }
    });
  }

  const themeCheckbox = document.getElementById("theme-checkbox");
  if (themeCheckbox) {
    themeCheckbox.addEventListener("change", function () {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      document.documentElement.setAttribute('data-theme', currentTheme === 'dark' ? 'light' : 'dark');
    });
  }
});