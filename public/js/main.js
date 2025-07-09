document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM fully loaded");

  const authModal = document.getElementById("auth-modal");
  const loginForm = document.getElementById("login-form");
  const registerForm = document.getElementById("register-form");
  const showLoginBtn = document.getElementById("show-login");
  const showLoginNavBtn = document.getElementById("show-login-btn");
  const showRegisterBtn = document.getElementById("show-register");
  const ctaRegisterBtn = document.getElementById("cta-register");
  const closeModalBtn = document.getElementById("close-modal");
  const switchToRegisterBtn = document.getElementById("switch-to-register");
  const mobileMenuBtn = document.getElementById("mobile-menu-btn");
  const navMenu = document.getElementById("nav-menu");
  const themeToggle = document.getElementById("theme-toggle");
  const themeIcon = document.getElementById("theme-icon");

  console.log("Modal elements:", { authModal, loginForm, registerForm });
  console.log("Button elements:", {
    showLoginBtn,
    showLoginNavBtn,
    showRegisterBtn,
    ctaRegisterBtn,
  });

  function openModal(form) {
    console.log("Opening modal with form:", form);
    if (!authModal) {
      console.error("Auth modal not found!");
      return;
    }

    authModal.style.display = "flex";

    if (form === "login") {
      if (loginForm) loginForm.style.display = "block";
      if (registerForm) registerForm.style.display = "none";
    } else if (form === "register") {
      if (loginForm) loginForm.style.display = "none";
      if (registerForm) registerForm.style.display = "block";
    }
  }

  function closeModal() {
    console.log("Closing modal");
    if (authModal) {
      authModal.style.display = "none";
    }
  }

  if (showLoginBtn) {
    console.log("Adding event listener to showLoginBtn");
    showLoginBtn.addEventListener("click", function (e) {
      e.preventDefault();
      openModal("login");
    });
  }

  if (showLoginNavBtn) {
    console.log("Adding event listener to showLoginNavBtn");
    showLoginNavBtn.addEventListener("click", function (e) {
      e.preventDefault();
      openModal("login");
    });
  }

  if (showRegisterBtn) {
    console.log("Adding event listener to showRegisterBtn");
    showRegisterBtn.addEventListener("click", function (e) {
      e.preventDefault();
      openModal("register");
    });
  }

  if (ctaRegisterBtn) {
    console.log("Adding event listener to ctaRegisterBtn");
    ctaRegisterBtn.addEventListener("click", function (e) {
      e.preventDefault();
      openModal("register");
    });
  }

  if (closeModalBtn) {
    console.log("Adding event listener to closeModalBtn");
    closeModalBtn.addEventListener("click", closeModal);
  }

  if (switchToRegisterBtn) {
    console.log("Adding event listener to switchToRegisterBtn");
    switchToRegisterBtn.addEventListener("click", function () {
      if (loginForm) loginForm.style.display = "none";
      if (registerForm) registerForm.style.display = "block";
    });
  }

  window.addEventListener("click", function (e) {
    if (e.target === authModal) {
      closeModal();
    }
  });

  const alertElement = document.querySelector(".alert");
  if (alertElement) {
    console.log("Alert found, opening modal automatically");
    authModal.style.display = "flex";

    if (
      alertElement.textContent.includes("contraseñas") ||
      alertElement.textContent.includes("registrar") ||
      alertElement.textContent.includes("Registro")
    ) {
      loginForm.style.display = "none";
      registerForm.style.display = "block";
    }
  }

  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener("click", function () {
      navMenu.classList.toggle("show");
    });
  }

  let darkMode = localStorage.getItem("darkMode") === "true";

  if (darkMode && themeIcon) {
    document.body.classList.add("dark-mode");
    themeIcon.classList.remove("bi-moon-fill");
    themeIcon.classList.add("bi-sun-fill");
  }

  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      darkMode = !darkMode;
      localStorage.setItem("darkMode", darkMode);

      if (darkMode) {
        document.body.classList.add("dark-mode");
        if (themeIcon) {
          themeIcon.classList.remove("bi-moon-fill");
          themeIcon.classList.add("bi-sun-fill");
        }
      } else {
        document.body.classList.remove("dark-mode");
        if (themeIcon) {
          themeIcon.classList.remove("bi-sun-fill");
          themeIcon.classList.add("bi-moon-fill");
        }
      }
    });
  }
});

document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has("newsletter")) {
    const newsletterElement = document.getElementById("newsletter");

    if (newsletterElement) {
      setTimeout(function () {
        newsletterElement.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      }, 100);
    }
  }
});
