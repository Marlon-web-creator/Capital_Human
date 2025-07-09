class AdminDashboard {
  constructor() {
    this.apiBase = "api/";
    this.init();
  }

  init() {
    this.loadDashboardData();
    this.initializeSearch();
    this.initializeThemeToggle();
    this.initializeCharts();
    this.setupEventListeners();

    setInterval(() => this.loadDashboardData(), 300000);
  }

  async loadDashboardData() {
    try {
      await Promise.all([
        this.loadMetrics(),
        this.loadRecentEmployees(),
        this.loadRecentPayrolls(),
        this.loadNotifications(),
      ]);
    } catch (error) {
      console.error("Error cargando datos del dashboard:", error);
      this.showNotification("Error al cargar los datos", "error");
    }
  }

  async loadMetrics() {
    try {
      const response = await fetch(`${this.apiBase}dashboard_metrics.php`);
      const data = await response.json();

      if (data.success) {
        this.updateMetricCard(
          "empleados-activos",
          data.empleados_activos,
          "empleados.php"
        );
        this.updateMetricCard(
          "nominas-pendientes",
          data.nominas_pendientes,
          "nominas.php"
        );
        this.updateMetricCard(
          "asistencias-hoy",
          data.asistencias_hoy,
          "asistencias.php"
        );
        this.updateMetricCard(
          "mensajes-nuevos",
          data.mensajes_nuevos,
          "contacto.php"
        );
      }
    } catch (error) {
      console.error("Error cargando métricas:", error);
    }
  }

  updateMetricCard(type, value, link) {
    const cards = document.querySelectorAll(".metric-card");
    cards.forEach((card) => {
      const number = card.querySelector(".metric-number");
      const cardLink = card.querySelector(".card-link");

      if (
        type === "empleados-activos" &&
        card.querySelector(".employee-icon")
      ) {
        number.textContent = value;
        cardLink.href = link;
      } else if (
        type === "nominas-pendientes" &&
        card.querySelector(".payroll-icon")
      ) {
        number.textContent = value;
        cardLink.href = link;
      } else if (
        type === "asistencias-hoy" &&
        card.querySelector(".attendance-icon")
      ) {
        number.textContent = value;
        cardLink.href = link;
      } else if (
        type === "mensajes-nuevos" &&
        card.querySelector(".message-icon")
      ) {
        number.textContent = value;
        cardLink.href = link;
      }
    });
  }

  async loadRecentEmployees() {
    try {
      const response = await fetch(`${this.apiBase}recent_employees.php`);
      const data = await response.json();

      if (data.success) {
        this.updateRecentEmployeesTable(data.employees);
      }
    } catch (error) {
      console.error("Error cargando empleados recientes:", error);
    }
  }

  updateRecentEmployeesTable(employees) {
    const tbody = document.querySelector(
      ".table-card:first-child .data-table tbody"
    );
    if (!tbody) return;

    tbody.innerHTML = "";

    employees.forEach((employee) => {
      const row = document.createElement("tr");
      row.innerHTML = `
                <td>${employee.nombre_completo}</td>
                <td>${employee.departamento || "Sin asignar"}</td>
                <td>${this.formatDate(employee.fecha_contratacion)}</td>
                <td class="actions">
                    <a href="empleado_editar.php?id=${
                      employee.id
                    }" class="btn-icon" title="Editar">
                        <i class="bi bi-pencil-square"></i>
                    </a>
                    <a href="empleado_detalle.php?id=${
                      employee.id
                    }" class="btn-icon" title="Ver detalles">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            `;
      tbody.appendChild(row);
    });
  }

  async loadRecentPayrolls() {
    try {
      const response = await fetch(`${this.apiBase}recent_payrolls.php`);
      const data = await response.json();

      if (data.success) {
        this.updateRecentPayrollsTable(data.payrolls);
      }
    } catch (error) {
      console.error("Error cargando nóminas recientes:", error);
    }
  }

  updateRecentPayrollsTable(payrolls) {
    const tbody = document.querySelector(
      ".table-card:last-child .data-table tbody"
    );
    if (!tbody) return;

    tbody.innerHTML = "";

    payrolls.forEach((payroll) => {
      const row = document.createElement("tr");
      const statusClass =
        payroll.estado === "pagada" ? "status-success" : "status-pending";
      const statusText = payroll.estado === "pagada" ? "Pagada" : "Pendiente";

      row.innerHTML = `
                <td>${payroll.empleado_nombre}</td>
                <td>${this.formatDate(
                  payroll.periodo_inicio
                )} - ${this.formatDate(payroll.periodo_fin)}</td>
                <td>${this.formatCurrency(payroll.total)}</td>
                <td>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </td>
            `;
      tbody.appendChild(row);
    });
  }

  async loadNotifications() {
    try {
      const response = await fetch(`${this.apiBase}notifications.php`);
      const data = await response.json();

      if (data.success) {
        this.updateNotificationBadge(data.count);
      }
    } catch (error) {
      console.error("Error cargando notificaciones:", error);
    }
  }

  updateNotificationBadge(count) {
    const badge = document.querySelector(".notification-icon .badge");
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? "block" : "none";
    }
  }

  initializeSearch() {
    const searchInput = document.querySelector(".search-box input");
    if (searchInput) {
      let searchTimeout;

      searchInput.addEventListener("input", (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          this.performSearch(e.target.value);
        }, 300);
      });
    }
  }

  async performSearch(query) {
    if (query.length < 2) return;

    try {
      const response = await fetch(`${this.apiBase}search.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ query: query }),
      });

      const data = await response.json();

      if (data.success) {
        this.showSearchResults(data.results);
      }
    } catch (error) {
      console.error("Error en búsqueda:", error);
    }
  }

  showSearchResults(results) {
    let dropdown = document.querySelector(".search-dropdown");

    if (!dropdown) {
      dropdown = document.createElement("div");
      dropdown.className = "search-dropdown";
      document.querySelector(".search-box").appendChild(dropdown);
    }

    dropdown.innerHTML = "";

    if (results.length === 0) {
      dropdown.innerHTML =
        '<div class="search-item">No se encontraron resultados</div>';
    } else {
      results.forEach((result) => {
        const item = document.createElement("div");
        item.className = "search-item";
        item.innerHTML = `
                    <i class="bi bi-${
                      result.type === "employee" ? "person" : "cash-stack"
                    }"></i>
                    <span>${result.name}</span>
                `;
        item.addEventListener("click", () => {
          window.location.href = result.url;
        });
        dropdown.appendChild(item);
      });
    }

    dropdown.style.display = "block";
  }

  initializeThemeToggle() {
    const themeToggle = document.getElementById("theme-checkbox");
    if (themeToggle) {
      const savedTheme = localStorage.getItem("admin-theme") || "light";
      this.applyTheme(savedTheme);
      themeToggle.checked = savedTheme === "dark";

      themeToggle.addEventListener("change", (e) => {
        const theme = e.target.checked ? "dark" : "light";
        this.applyTheme(theme);
        localStorage.setItem("admin-theme", theme);
      });
    }
  }

  applyTheme(theme) {
    document.body.setAttribute("data-theme", theme);
  }

  async initializeCharts() {
    try {
      await this.loadChartData();
    } catch (error) {
      console.error("Error inicializando gráficos:", error);
    }
  }

  async loadChartData() {
    try {
      const [departmentData, payrollData] = await Promise.all([
        fetch(`${this.apiBase}chart_departments.php`).then((r) => r.json()),
        fetch(`${this.apiBase}chart_payrolls.php`).then((r) => r.json()),
      ]);

      if (departmentData.success) {
        this.createDepartmentChart(departmentData.data);
      }

      if (payrollData.success) {
        this.createPayrollChart(payrollData.data);
      }
    } catch (error) {
      console.error("Error cargando datos de gráficos:", error);
    }
  }

  createDepartmentChart(data) {
    const container = document.getElementById("departamentos-chart");
    if (!container) return;

    const canvas = document.createElement("canvas");
    canvas.width = 300;
    canvas.height = 200;
    container.innerHTML = "";
    container.appendChild(canvas);

    const ctx = canvas.getContext("2d");
    this.drawPieChart(ctx, data, canvas.width, canvas.height);
  }

  createPayrollChart(data) {
    const container = document.getElementById("nominas-chart");
    if (!container) return;

    const canvas = document.createElement("canvas");
    canvas.width = 300;
    canvas.height = 200;
    container.innerHTML = "";
    container.appendChild(canvas);

    const ctx = canvas.getContext("2d");
    this.drawBarChart(ctx, data, canvas.width, canvas.height);
  }

  drawPieChart(ctx, data, width, height) {
    const centerX = width / 2;
    const centerY = height / 2;
    const radius = Math.min(width, height) / 3;

    let total = data.reduce((sum, item) => sum + item.value, 0);
    let currentAngle = 0;

    const colors = ["#3498db", "#e74c3c", "#f39c12", "#27ae60", "#9b59b6"];

    data.forEach((item, index) => {
      const sliceAngle = (item.value / total) * 2 * Math.PI;

      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(
        centerX,
        centerY,
        radius,
        currentAngle,
        currentAngle + sliceAngle
      );
      ctx.closePath();
      ctx.fillStyle = colors[index % colors.length];
      ctx.fill();

      currentAngle += sliceAngle;
    });

    this.addChartLegend(ctx, data, colors, width, height);
  }

  drawBarChart(ctx, data, width, height) {
    const margin = 40;
    const chartWidth = width - 2 * margin;
    const chartHeight = height - 2 * margin;

    const maxValue = Math.max(...data.map((item) => item.value));
    const barWidth = chartWidth / data.length;

    ctx.fillStyle = "#3498db";

    data.forEach((item, index) => {
      const barHeight = (item.value / maxValue) * chartHeight;
      const x = margin + index * barWidth + barWidth * 0.1;
      const y = height - margin - barHeight;

      ctx.fillRect(x, y, barWidth * 0.8, barHeight);

      ctx.fillStyle = "#333";
      ctx.font = "12px Arial";
      ctx.textAlign = "center";
      ctx.fillText(item.label, x + barWidth * 0.4, height - margin + 15);
      ctx.fillStyle = "#3498db";
    });
  }

  addChartLegend(ctx, data, colors, width, height) {
    const legendX = width - 120;
    let legendY = 20;

    ctx.font = "12px Arial";
    ctx.textAlign = "left";

    data.forEach((item, index) => {
      ctx.fillStyle = colors[index % colors.length];
      ctx.fillRect(legendX, legendY, 12, 12);

      ctx.fillStyle = "#333";
      ctx.fillText(item.label, legendX + 20, legendY + 10);

      legendY += 20;
    });
  }

  setupEventListeners() {
    document.addEventListener("click", (e) => {
      const searchBox = document.querySelector(".search-box");
      const dropdown = document.querySelector(".search-dropdown");

      if (dropdown && !searchBox.contains(e.target)) {
        dropdown.style.display = "none";
      }
    });

    document.querySelectorAll(".chart-action").forEach((button) => {
      button.addEventListener("click", (e) => {
        const action = e.target.closest("button");
        if (action.querySelector(".bi-download")) {
          this.downloadChart(action.closest(".chart-card"));
        } else if (action.querySelector(".bi-three-dots-vertical")) {
          this.showChartOptions(action);
        }
      });
    });

    this.setupAutoRefresh();
  }

  setupAutoRefresh() {
    setInterval(() => {
      this.loadNotifications();
    }, 30000);

    setInterval(() => {
      this.loadMetrics();
    }, 120000);
  }

  downloadChart(chartCard) {
    const canvas = chartCard.querySelector("canvas");
    if (canvas) {
      const link = document.createElement("a");
      link.download = "chart.png";
      link.href = canvas.toDataURL();
      link.click();
    }
  }

  showChartOptions(button) {
    console.log("Mostrar opciones de gráfico");
  }

  formatDate(dateString) {
    if (!dateString) return "N/A";
    const date = new Date(dateString);
    return date.toLocaleDateString("es-CO");
  }

  formatCurrency(amount) {
    return new Intl.NumberFormat("es-CO", {
      style: "currency",
      currency: "COP",
      minimumFractionDigits: 0,
    }).format(amount);
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
            <i class="bi bi-${
              type === "error" ? "exclamation-triangle" : "info-circle"
            }"></i>
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        `;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.remove();
    }, 5000);

    notification
      .querySelector(".notification-close")
      .addEventListener("click", () => {
        notification.remove();
      });
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new AdminDashboard();
});

const additionalStyles = `
<style>
.search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 1000;
    display: none;
    max-height: 300px;
    overflow-y: auto;
}

.search-item {
    padding: 10px 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-item:hover {
    background-color: #f5f5f5;
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 15px 20px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #3498db;
    z-index: 1001;
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: 400px;
}

.notification-error {
    border-left-color: #e74c3c;
}

.notification-success {
    border-left-color: #27ae60;
}

.notification-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    margin-left: auto;
}

[data-theme="dark"] {
    --bg-color: #1a1a1a;
    --text-color: #ffffff;
    --card-bg: #2d2d2d;
}

[data-theme="dark"] .search-dropdown {
    background: var(--card-bg);
    border-color: #444;
    color: var(--text-color);
}

[data-theme="dark"] .search-item:hover {
    background-color: #444;
}

[data-theme="dark"] .notification {
    background: var(--card-bg);
    color: var(--text-color);
}
</style>
`;

document.head.insertAdjacentHTML("beforeend", additionalStyles);
