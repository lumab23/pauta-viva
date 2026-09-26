"use strict";

const API_URL = "/api/pautas";
const PAGE_SIZE = 10;

const STATUS = {
  ideia: { label: "Ideia", className: "status-ideia" },
  em_apuracao: { label: "Em apuração", className: "status-em_apuracao" },
  publicada: { label: "Publicada", className: "status-publicada" },
};

const state = {
  page: 1,
  filters: { titulo: "", editoria: "", status: "" },
  items: [],
  total: 0,
  editingId: null,
  currentDetail: null,
  pendingDelete: null,
  requestSequence: 0,
};

const elements = {
  filterForm: document.querySelector("#filter-form"),
  filterTitle: document.querySelector("#filter-title"),
  filterEditoria: document.querySelector("#filter-editoria"),
  filterStatus: document.querySelector("#filter-status"),
  clearFiltersButton: document.querySelector("#clear-filters-button"),
  retryButton: document.querySelector("#retry-button"),
  newPautaButton: document.querySelector("#new-pauta-button"),
  emptyActionButton: document.querySelector("#empty-action-button"),
  loadingState: document.querySelector("#loading-state"),
  errorState: document.querySelector("#error-state"),
  errorStateMessage: document.querySelector("#error-state-message"),
  emptyState: document.querySelector("#empty-state"),
  emptyTitle: document.querySelector("#empty-title"),
  emptyMessage: document.querySelector("#empty-message"),
  tableRegion: document.querySelector("#table-region"),
  tableBody: document.querySelector("#pautas-body"),
  resultCount: document.querySelector("#result-count"),
  pagination: document.querySelector("#pagination"),
  previousPageButton: document.querySelector("#previous-page-button"),
  nextPageButton: document.querySelector("#next-page-button"),
  pageIndicator: document.querySelector("#page-indicator"),
  editoriaOptions: document.querySelector("#editoria-options"),
  summaryIdeia: document.querySelector("#summary-ideia"),
  summaryEmApuracao: document.querySelector("#summary-em-apuracao"),
  summaryPublicada: document.querySelector("#summary-publicada"),
  pautaDialog: document.querySelector("#pauta-dialog"),
  pautaForm: document.querySelector("#pauta-form"),
  formEyebrow: document.querySelector("#form-eyebrow"),
  formTitle: document.querySelector("#form-title"),
  formError: document.querySelector("#form-error"),
  savePautaButton: document.querySelector("#save-pauta-button"),
  pautaTitulo: document.querySelector("#pauta-titulo"),
  pautaDescricao: document.querySelector("#pauta-descricao"),
  pautaEditoria: document.querySelector("#pauta-editoria"),
  pautaStatus: document.querySelector("#pauta-status"),
  pautaPrazo: document.querySelector("#pauta-prazo"),
  descriptionCount: document.querySelector("#description-count"),
  detailDialog: document.querySelector("#detail-dialog"),
  detailLoading: document.querySelector("#detail-loading"),
  detailError: document.querySelector("#detail-error"),
  detailContent: document.querySelector("#detail-content"),
  detailTitle: document.querySelector("#detail-title"),
  detailStatus: document.querySelector("#detail-status"),
  detailEditoria: document.querySelector("#detail-editoria"),
  detailDescription: document.querySelector("#detail-description"),
  detailDeadline: document.querySelector("#detail-deadline"),
  detailCreated: document.querySelector("#detail-created"),
  detailUpdated: document.querySelector("#detail-updated"),
  detailEditButton: document.querySelector("#detail-edit-button"),
  detailDeleteButton: document.querySelector("#detail-delete-button"),
  deleteDialog: document.querySelector("#delete-dialog"),
  deleteForm: document.querySelector("#delete-form"),
  deleteMessage: document.querySelector("#delete-message"),
  deleteError: document.querySelector("#delete-error"),
  cancelDeleteButton: document.querySelector("#cancel-delete-button"),
  confirmDeleteButton: document.querySelector("#confirm-delete-button"),
  toast: document.querySelector("#toast"),
};

class ApiError extends Error {
  constructor(message, status = 0, fields = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.fields = fields;
  }
}

async function request(path = "", options = {}) {
  const headers = new Headers(options.headers || {});
  headers.set("Accept", "application/json");

  if (options.body) {
    headers.set("Content-Type", "application/json");
  }

  let response;

  try {
    response = await fetch(`${API_URL}${path}`, { ...options, headers });
  } catch (error) {
    throw new ApiError("Não foi possível conectar à API. Verifique se o servidor está ativo.");
  }

  if (response.status === 204) {
    return null;
  }

  let payload = null;

  try {
    payload = await response.json();
  } catch (error) {
    throw new ApiError("A API retornou uma resposta inválida.", response.status);
  }

  if (!response.ok) {
    throw new ApiError(
      payload?.error?.message || "Não foi possível concluir a operação.",
      response.status,
      payload?.error?.fields || {},
    );
  }

  return payload;
}

function buildListQuery(filters = state.filters, page = state.page, perPage = PAGE_SIZE) {
  const query = new URLSearchParams({
    pagina: String(page),
    por_pagina: String(perPage),
  });

  Object.entries(filters).forEach(([key, value]) => {
    const normalized = value.trim();
    if (normalized) query.set(key, normalized);
  });

  return `?${query.toString()}`;
}

async function loadPautas() {
  const sequence = ++state.requestSequence;
  setListState("loading");
  elements.resultCount.textContent = "";
  loadSummary();

  try {
    const payload = await request(buildListQuery());

    if (sequence !== state.requestSequence) return;

    state.items = Array.isArray(payload?.data) ? payload.data : [];
    state.total = Number(payload?.meta?.total || 0);
    renderList();
  } catch (error) {
    if (sequence !== state.requestSequence) return;
    setListState("error", readableError(error));
  }
}

async function loadSummary() {
  const sequence = state.requestSequence;
  const baseFilters = {
    titulo: state.filters.titulo,
    editoria: state.filters.editoria,
    status: "",
  };

  const targets = [
    ["ideia", elements.summaryIdeia],
    ["em_apuracao", elements.summaryEmApuracao],
    ["publicada", elements.summaryPublicada],
  ];

  targets.forEach(([, target]) => { target.textContent = "…"; });

  try {
    // Usa o total de cada filtro para montar o resumo sem carregar as listas.
    const results = await Promise.all(
      targets.map(([status]) => request(buildListQuery({ ...baseFilters, status }, 1, 1))),
    );

    if (sequence !== state.requestSequence) return;

    results.forEach((payload, index) => {
      targets[index][1].textContent = formatNumber(Number(payload?.meta?.total || 0));
    });
  } catch (error) {
    if (sequence !== state.requestSequence) return;
    targets.forEach(([, target]) => { target.textContent = "—"; });
  }
}

function renderList() {
  elements.tableBody.replaceChildren();
  updateEditoriaOptions(state.items);

  if (state.items.length === 0) {
    setListState("empty");
    renderPagination();
    return;
  }

  state.items.forEach((pauta) => {
    elements.tableBody.append(createPautaRow(pauta));
  });

  elements.resultCount.textContent = `${formatNumber(state.total)} ${state.total === 1 ? "pauta encontrada" : "pautas encontradas"}`;
  setListState("ready");
  renderPagination();
}

function createPautaRow(pauta) {
  const row = document.createElement("tr");

  // Usa textContent para não renderizar HTML recebido da API.
  const titleCell = document.createElement("td");
  const titleButton = document.createElement("button");
  titleButton.type = "button";
  titleButton.className = "title-button";
  titleButton.textContent = pauta.titulo;
  titleButton.addEventListener("click", () => openDetails(pauta.id));
  titleCell.append(titleButton);

  const editoriaCell = document.createElement("td");
  editoriaCell.dataset.label = "Editoria";
  const editoria = document.createElement("span");
  editoria.className = "editoria-text";
  editoria.textContent = pauta.editoria;
  editoriaCell.append(editoria);

  const statusCell = document.createElement("td");
  statusCell.dataset.label = "Status";
  statusCell.append(createStatusBadge(pauta.status));

  const deadlineCell = document.createElement("td");
  deadlineCell.dataset.label = "Prazo";
  deadlineCell.append(createDateElement(pauta.prazo, "Sem prazo"));

  const actionsCell = document.createElement("td");
  const actions = document.createElement("div");
  actions.className = "row-actions";
  actions.append(
    createActionButton("Ver", pauta.titulo, () => openDetails(pauta.id)),
    createActionButton("Editar", pauta.titulo, () => openForm(pauta)),
    createActionButton("Excluir", pauta.titulo, () => openDeleteConfirmation(pauta), true),
  );
  actionsCell.append(actions);

  row.append(titleCell, editoriaCell, statusCell, deadlineCell, actionsCell);
  return row;
}

function createActionButton(label, title, action, destructive = false) {
  const button = document.createElement("button");
  button.type = "button";
  button.className = `table-action${destructive ? " delete" : ""}`;
  button.textContent = label;
  button.setAttribute("aria-label", `${label} pauta ${title}`);
  button.addEventListener("click", action);
  return button;
}

function createStatusBadge(status) {
  const definition = STATUS[status] || { label: status, className: "" };
  const badge = document.createElement("span");
  badge.className = `status-badge ${definition.className}`.trim();
  badge.textContent = definition.label;
  return badge;
}

function createDateElement(value, fallback) {
  const element = value ? document.createElement("time") : document.createElement("span");
  element.className = "deadline-text";

  if (!value) {
    element.textContent = fallback;
    return element;
  }

  element.dateTime = value;
  element.textContent = formatDate(value);
  return element;
}

function renderPagination() {
  const pages = Math.max(1, Math.ceil(state.total / PAGE_SIZE));
  const hasResults = state.total > 0;

  elements.pagination.hidden = !hasResults;
  elements.pageIndicator.textContent = `Página ${state.page} de ${pages}`;
  elements.previousPageButton.disabled = state.page <= 1;
  elements.nextPageButton.disabled = state.page >= pages;
}

function setListState(view, message = "") {
  elements.loadingState.hidden = view !== "loading";
  elements.errorState.hidden = view !== "error";
  elements.emptyState.hidden = view !== "empty";
  elements.tableRegion.hidden = view !== "ready";

  if (view === "loading") elements.pagination.hidden = true;

  if (view === "error") {
    elements.errorStateMessage.textContent = message;
    elements.pagination.hidden = true;
  }

  if (view === "empty") {
    const filtered = hasActiveFilters();
    elements.emptyTitle.textContent = filtered ? "Nenhuma pauta encontrada" : "Nenhuma pauta cadastrada";
    elements.emptyMessage.textContent = filtered
      ? "Revise os filtros para ampliar os resultados."
      : "Crie a primeira pauta para começar a organizar o trabalho.";
    elements.emptyActionButton.textContent = filtered ? "Limpar filtros" : "Criar pauta";
    elements.resultCount.textContent = "0 pautas encontradas";
  }
}

function updateEditoriaOptions(items) {
  const editorias = new Set(
    Array.from(elements.editoriaOptions.options, (option) => option.value),
  );

  items.forEach((item) => editorias.add(item.editoria));
  elements.editoriaOptions.replaceChildren();

  Array.from(editorias).sort((a, b) => a.localeCompare(b, "pt-BR")).forEach((name) => {
    const option = document.createElement("option");
    option.value = name;
    elements.editoriaOptions.append(option);
  });
}

async function openDetails(id) {
  elements.detailTitle.textContent = "Carregando…";
  elements.detailLoading.hidden = false;
  elements.detailError.hidden = true;
  elements.detailContent.hidden = true;
  state.currentDetail = null;
  elements.detailDialog.showModal();

  try {
    const payload = await request(`/${id}`);
    state.currentDetail = payload.data;
    renderDetails(payload.data);
  } catch (error) {
    elements.detailLoading.hidden = true;
    elements.detailTitle.textContent = "Detalhes indisponíveis";
    elements.detailError.textContent = readableError(error);
    elements.detailError.hidden = false;
  }
}

function renderDetails(pauta) {
  const definition = STATUS[pauta.status] || { label: pauta.status, className: "" };

  elements.detailTitle.textContent = pauta.titulo;
  elements.detailStatus.className = `status-badge ${definition.className}`.trim();
  elements.detailStatus.textContent = definition.label;
  elements.detailEditoria.textContent = pauta.editoria;
  elements.detailDescription.textContent = pauta.descricao;
  elements.detailDeadline.textContent = pauta.prazo ? formatDate(pauta.prazo) : "Sem prazo";
  elements.detailCreated.textContent = formatDate(pauta.criado_em);
  elements.detailUpdated.textContent = formatDate(pauta.atualizado_em);
  elements.detailLoading.hidden = true;
  elements.detailError.hidden = true;
  elements.detailContent.hidden = false;
}

function openForm(pauta = null) {
  state.editingId = pauta?.id || null;
  elements.pautaForm.reset();
  clearFormErrors();

  if (pauta) {
    elements.formEyebrow.textContent = "Editar pauta";
    elements.formTitle.textContent = "Atualizar pauta";
    elements.savePautaButton.textContent = "Salvar alterações";
    elements.pautaTitulo.value = pauta.titulo;
    elements.pautaDescricao.value = pauta.descricao;
    elements.pautaEditoria.value = pauta.editoria;
    elements.pautaStatus.value = pauta.status;
    elements.pautaPrazo.value = toDateTimeLocal(pauta.prazo);
  } else {
    elements.formEyebrow.textContent = "Nova pauta";
    elements.formTitle.textContent = "Cadastrar pauta";
    elements.savePautaButton.textContent = "Salvar pauta";
    elements.pautaStatus.value = "ideia";
  }

  updateDescriptionCount();
  elements.pautaDialog.showModal();
  window.setTimeout(() => elements.pautaTitulo.focus(), 0);
}

async function savePauta(event) {
  event.preventDefault();
  clearFormErrors();

  if (!elements.pautaForm.checkValidity()) {
    elements.pautaForm.reportValidity();
    return;
  }

  const deadline = elements.pautaPrazo.value;
  const body = {
    titulo: elements.pautaTitulo.value,
    descricao: elements.pautaDescricao.value,
    editoria: elements.pautaEditoria.value,
    status: elements.pautaStatus.value,
    prazo: deadline ? toIsoDeadline(deadline) : null,
  };
  const editing = state.editingId !== null;

  setButtonBusy(elements.savePautaButton, true, editing ? "Salvando…" : "Criando…");

  try {
    await request(editing ? `/${state.editingId}` : "", {
      method: editing ? "PUT" : "POST",
      body: JSON.stringify(body),
    });

    elements.pautaDialog.close();
    if (!editing) state.page = 1;
    showToast(editing ? "Pauta atualizada com sucesso." : "Pauta criada com sucesso.");
    await loadPautas();
  } catch (error) {
    showFormError(error);
  } finally {
    setButtonBusy(elements.savePautaButton, false, editing ? "Salvar alterações" : "Salvar pauta");
  }
}

function showFormError(error) {
  const fields = error instanceof ApiError ? error.fields : {};
  let hasFieldError = false;

  Object.entries(fields).forEach(([field, messages]) => {
    const input = elements.pautaForm.elements.namedItem(field);
    const errorElement = document.querySelector(`#pauta-${field}-error`);

    if (input instanceof HTMLElement && errorElement) {
      input.setAttribute("aria-invalid", "true");
      errorElement.textContent = Array.isArray(messages) ? messages[0] : String(messages);
      hasFieldError = true;
    }
  });

  elements.formError.textContent = hasFieldError
    ? "Revise os campos indicados."
    : readableError(error);
  elements.formError.hidden = false;
}

function clearFormErrors() {
  elements.formError.hidden = true;
  elements.formError.textContent = "";

  elements.pautaForm.querySelectorAll("[aria-invalid]").forEach((input) => {
    input.removeAttribute("aria-invalid");
  });

  elements.pautaForm.querySelectorAll(".field-error").forEach((error) => {
    error.textContent = "";
  });
}

function openDeleteConfirmation(pauta) {
  state.pendingDelete = pauta;
  elements.deleteError.hidden = true;
  elements.deleteError.textContent = "";
  elements.deleteMessage.textContent = `A pauta “${pauta.titulo}” será excluída permanentemente.`;
  elements.deleteDialog.showModal();
}

async function deletePauta(event) {
  event.preventDefault();
  if (!state.pendingDelete) return;

  setButtonBusy(elements.confirmDeleteButton, true, "Excluindo…");
  elements.deleteError.hidden = true;

  try {
    await request(`/${state.pendingDelete.id}`, { method: "DELETE" });
    elements.deleteDialog.close();

    if (state.items.length === 1 && state.page > 1) {
      state.page -= 1;
    }

    state.pendingDelete = null;
    showToast("Pauta excluída com sucesso.");
    await loadPautas();
  } catch (error) {
    elements.deleteError.textContent = readableError(error);
    elements.deleteError.hidden = false;
  } finally {
    setButtonBusy(elements.confirmDeleteButton, false, "Excluir pauta");
  }
}

function applyFilters(event) {
  event.preventDefault();
  state.filters = {
    titulo: elements.filterTitle.value,
    editoria: elements.filterEditoria.value,
    status: elements.filterStatus.value,
  };
  state.page = 1;
  loadPautas();
}

function clearFilters() {
  elements.filterForm.reset();
  state.filters = { titulo: "", editoria: "", status: "" };
  state.page = 1;
  loadPautas();
}

function hasActiveFilters() {
  return Object.values(state.filters).some((value) => value.trim() !== "");
}

function setButtonBusy(button, busy, label) {
  button.disabled = busy;
  button.textContent = label;
}

function updateDescriptionCount() {
  elements.descriptionCount.textContent = `${formatNumber(elements.pautaDescricao.value.length)} / 10.000`;
}

function formatDate(value) {
  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return "Data inválida";

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function toDateTimeLocal(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const pad = (number) => String(number).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toIsoDeadline(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return date.toISOString().replace(".000Z", "Z");
}

function formatNumber(value) {
  return new Intl.NumberFormat("pt-BR").format(value);
}

function readableError(error) {
  return error instanceof ApiError
    ? error.message
    : "Ocorreu um erro inesperado. Tente novamente.";
}

let toastTimer;

function showToast(message, type = "success") {
  window.clearTimeout(toastTimer);
  elements.toast.textContent = message;
  elements.toast.className = `toast${type === "error" ? " error" : ""}`;
  elements.toast.hidden = false;
  toastTimer = window.setTimeout(() => { elements.toast.hidden = true; }, 4500);
}

elements.filterForm.addEventListener("submit", applyFilters);
elements.clearFiltersButton.addEventListener("click", clearFilters);
elements.retryButton.addEventListener("click", loadPautas);
elements.newPautaButton.addEventListener("click", () => openForm());
elements.emptyActionButton.addEventListener("click", () => {
  if (hasActiveFilters()) clearFilters();
  else openForm();
});
elements.previousPageButton.addEventListener("click", () => {
  if (state.page > 1) {
    state.page -= 1;
    loadPautas();
  }
});
elements.nextPageButton.addEventListener("click", () => {
  const pages = Math.ceil(state.total / PAGE_SIZE);
  if (state.page < pages) {
    state.page += 1;
    loadPautas();
  }
});
elements.pautaForm.addEventListener("submit", savePauta);
elements.pautaDescricao.addEventListener("input", updateDescriptionCount);
elements.deleteForm.addEventListener("submit", deletePauta);
elements.cancelDeleteButton.addEventListener("click", () => elements.deleteDialog.close());
elements.detailEditButton.addEventListener("click", () => {
  const pauta = state.currentDetail;
  if (!pauta) return;
  elements.detailDialog.close();
  openForm(pauta);
});
elements.detailDeleteButton.addEventListener("click", () => {
  const pauta = state.currentDetail;
  if (!pauta) return;
  elements.detailDialog.close();
  openDeleteConfirmation(pauta);
});

document.querySelectorAll("[data-close-dialog]").forEach((button) => {
  button.addEventListener("click", () => {
    const dialog = document.querySelector(`#${button.dataset.closeDialog}`);
    if (dialog instanceof HTMLDialogElement) dialog.close();
  });
});

elements.deleteDialog.addEventListener("close", () => {
  if (!elements.confirmDeleteButton.disabled) state.pendingDelete = null;
});

loadPautas();
