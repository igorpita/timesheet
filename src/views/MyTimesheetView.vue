<template>
  <div class="ts-view">
    <!-- Header com Totais e Ações -->
    <div class="ts-view-header">
      <div>
        <h2>Meu Timesheet</h2>
        <p class="ts-subtitle">Acompanhamento e registro das suas atividades em projetos e chamados</p>
      </div>
      <div class="ts-header-actions">
        <!-- Alternador de Visualização -->
        <div class="ts-view-toggle">
          <button
            class="ts-toggle-btn"
            :class="{ active: viewMode === 'list' }"
            @click="viewMode = 'list'"
          >
            📋 Lista
          </button>
          <button
            class="ts-toggle-btn"
            :class="{ active: viewMode === 'week' }"
            @click="viewMode = 'week'"
          >
            📅 Visão Semanal
          </button>
        </div>

        <button class="ts-btn ts-btn-primary" @click="$emit('open-modal')">
          ➕ Novo Apontamento
        </button>
      </div>
    </div>

    <!-- Cards de Resumo Rápido -->
    <div class="ts-summary-grid">
      <div class="ts-stat-card">
        <span class="ts-stat-label">Hoje</span>
        <span class="ts-stat-value">{{ todayHoursFormatted }}</span>
        <span class="ts-stat-sub">Meta diária: 8h00</span>
      </div>
      <div class="ts-stat-card">
        <span class="ts-stat-label">Esta Semana</span>
        <span class="ts-stat-value">{{ weekHoursFormatted }}</span>
        <span class="ts-stat-sub">{{ weekEntriesCount }} registros na semana</span>
      </div>
      <div class="ts-stat-card">
        <span class="ts-stat-label">Projetos Ativos</span>
        <span class="ts-stat-value">{{ activeProjectsCount }}</span>
        <span class="ts-stat-sub">Quadros com dedicação</span>
      </div>
      <div class="ts-stat-card">
        <span class="ts-stat-label">Total do Mês</span>
        <span class="ts-stat-value">{{ monthHoursFormatted }}</span>
        <span class="ts-stat-sub">{{ entries.length }} apontamentos no período</span>
      </div>
    </div>

    <!-- MODO 1: VISÃO SEMANAL EM GRADE -->
    <div v-if="viewMode === 'week'" class="ts-card">
      <div class="ts-card-header">
        <div class="ts-week-nav">
          <button class="ts-btn ts-btn-sm ts-btn-outline" @click="changeWeek(-1)">◀ Semana Anterior</button>
          <span class="ts-week-title">Semana de {{ formatShortDate(weekDays[0].date) }} a {{ formatShortDate(weekDays[6].date) }}</span>
          <button class="ts-btn ts-btn-sm ts-btn-outline" @click="changeWeek(1)">Próxima Semana ▶</button>
          <button class="ts-btn ts-btn-sm ts-btn-outline" @click="resetToCurrentWeek">Hoje</button>
        </div>
        <div class="ts-week-total">
          Total da Semana: <strong>{{ formatMinutes(totalWeekMinutes) }}</strong>
        </div>
      </div>

      <div class="ts-table-container">
        <table class="ts-table ts-week-table">
          <thead>
            <tr>
              <th class="ts-activity-col">Atividade / Cartão / Chamado</th>
              <th v-for="day in weekDays" :key="day.date" class="ts-day-col" :class="{ 'ts-today-col': day.isToday }">
                <div class="ts-day-header">
                  <span class="ts-day-name">{{ day.label }}</span>
                  <span class="ts-day-number">{{ day.dayNumber }}</span>
                </div>
              </th>
              <th class="ts-total-col">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="groupedActivities.length === 0">
              <td colspan="9" class="ts-empty-state">
                Nenhum apontamento nesta semana.
                <button class="ts-btn ts-btn-sm ts-btn-primary" style="margin-left: 10px;" @click="$emit('open-modal')">
                  Apontar Horas
                </button>
              </td>
            </tr>
            <tr v-for="act in groupedActivities" :key="act.key">
              <td class="ts-activity-cell">
                <span class="ts-badge ts-badge-deck">Deck</span>
                <span class="ts-act-title">{{ act.title }}</span>
              </td>
              <td v-for="day in weekDays" :key="day.date" class="ts-day-cell" :class="{ 'ts-today-col': day.isToday }">
                <span v-if="act.days[day.date]" class="ts-duration-pill">
                  {{ formatMinutes(act.days[day.date]) }}
                </span>
                <button
                  v-else-if="!day.isFuture"
                  class="ts-quick-add-btn"
                  title="Apontar neste dia"
                  @click="quickAddOnDate(act, day.date)"
                >
                  +
                </button>
                <span
                  v-else
                  class="ts-quick-add-disabled"
                  title="Não é permitido registrar apontamentos no futuro"
                >
                  —
                </span>
              </td>
              <td class="ts-total-cell">
                <strong>{{ formatMinutes(act.totalMinutes) }}</strong>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="ts-totals-row">
              <td><strong>Total Diário</strong></td>
              <td v-for="day in weekDays" :key="day.date" :class="{ 'ts-today-col': day.isToday }">
                <strong :class="getDayTotalClass(dayDailyTotals[day.date])">
                  {{ formatMinutes(dayDailyTotals[day.date] || 0) }}
                </strong>
              </td>
              <td><strong>{{ formatMinutes(totalWeekMinutes) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- MODO 2: VISÃO EM LISTA (TABELAR) -->
    <div v-else class="ts-card">
      <div class="ts-card-header">
        <h3>Histórico de Apontamentos</h3>
        <div class="ts-filters">
          <input
            type="date"
            v-model="filterStartDate"
            @change="loadEntries"
            class="ts-input ts-input-sm"
          />
          <span class="ts-separator">até</span>
          <input
            type="date"
            v-model="filterEndDate"
            @change="loadEntries"
            class="ts-input ts-input-sm"
          />
          <button class="ts-btn ts-btn-outline ts-btn-sm" @click="loadEntries">Filtrar</button>
        </div>
      </div>

      <div v-if="loading" class="ts-empty-state">
        <div class="ts-spinner"></div>
        <p>Carregando registros...</p>
      </div>

      <div v-else-if="entries.length === 0" class="ts-empty-state">
        <p>Nenhum apontamento encontrado no período selecionado.</p>
        <button class="ts-btn ts-btn-sm ts-btn-primary" @click="$emit('open-modal')">
          Registrar primeiro apontamento
        </button>
      </div>

      <div v-else class="ts-table-container">
        <table class="ts-table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Projeto / Cartão (Deck)</th>
              <th>Descrição</th>
              <th>Duração</th>
              <th>Status</th>
              <th class="ts-text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in entries" :key="entry.id">
              <td><strong>{{ formatDate(entry.workDate) }}</strong></td>
              <td>
                <div class="ts-ref-title">
                  {{ entry.externalTitle || ('Cartão #' + entry.externalId) }}
                </div>
                <small class="ts-ref-sub">ID: {{ entry.externalId }}</small>
              </td>
              <td class="ts-description-cell">
                {{ entry.description }}
              </td>
              <td>
                <span class="ts-duration-pill">{{ entry.durationFormatted }}</span>
              </td>
              <td>
                <span class="ts-status-dot" :class="entry.status"></span>
                {{ translateStatus(entry.status) }}
              </td>
              <td class="ts-text-right">
                <button
                  class="ts-btn-icon"
                  title="Auditoria / Histórico"
                  @click="$emit('view-audit', entry.id)"
                >
                  🕒
                </button>
                <button
                  class="ts-btn-icon"
                  title="Editar"
                  @click="$emit('edit-entry', entry)"
                >
                  ✏️
                </button>
                <button
                  class="ts-btn-icon ts-btn-danger"
                  title="Excluir"
                  @click="confirmDelete(entry.id)"
                >
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MyTimesheetView',
  props: {
    entries: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
  },
  emits: ['open-modal', 'edit-entry', 'delete-entry', 'filter-changed', 'view-audit', 'quick-log'],
  data() {
    const today = new Date();
    return {
      viewMode: 'week',
      currentWeekMonday: this.getMonday(today),
      filterStartDate: new Date(today.getFullYear(), today.getMonth(), 1).toISOString().substring(0, 10),
      filterEndDate: new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().substring(0, 10),
    };
  },
  computed: {
    weekDays() {
      const days = [];
      const labels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
      const todayStr = new Date().toISOString().substring(0, 10);

      for (let i = 0; i < 7; i++) {
        const d = new Date(this.currentWeekMonday);
        d.setDate(d.getDate() + i);
        const dateStr = d.toISOString().substring(0, 10);
        days.push({
          date: dateStr,
          label: labels[i],
          dayNumber: d.getDate(),
          isToday: dateStr === todayStr,
          isFuture: dateStr > todayStr
        });
      }
      return days;
    },
    groupedActivities() {
      const groups = {};
      const weekDates = this.weekDays.map(d => d.date);

      this.entries.forEach(entry => {
        if (!weekDates.includes(entry.workDate)) return;

        const key = `${entry.sourceType}-${entry.externalId}`;
        if (!groups[key]) {
          groups[key] = {
            key,
            sourceType: entry.sourceType,
            externalId: entry.externalId,
            title: entry.externalTitle || (`#${entry.externalId}`),
            days: {},
            totalMinutes: 0
          };
        }
        groups[key].days[entry.workDate] = (groups[key].days[entry.workDate] || 0) + (entry.durationMinutes || 0);
        groups[key].totalMinutes += (entry.durationMinutes || 0);
      });

      return Object.values(groups);
    },
    dayDailyTotals() {
      const totals = {};
      this.weekDays.forEach(d => {
        totals[d.date] = this.entries
          .filter(e => e.workDate === d.date)
          .reduce((sum, e) => sum + (e.durationMinutes || 0), 0);
      });
      return totals;
    },
    totalWeekMinutes() {
      return Object.values(this.dayDailyTotals).reduce((a, b) => a + b, 0);
    },
    todayHoursFormatted() {
      const today = new Date().toISOString().substring(0, 10);
      const mins = this.entries
        .filter(e => e.workDate === today)
        .reduce((sum, e) => sum + (e.durationMinutes || 0), 0);
      return this.formatMinutes(mins);
    },
    weekHoursFormatted() {
      return this.formatMinutes(this.totalWeekMinutes);
    },
    weekEntriesCount() {
      const weekDates = this.weekDays.map(d => d.date);
      return this.entries.filter(e => weekDates.includes(e.workDate)).length;
    },
    activeProjectsCount() {
      const set = new Set();
      this.entries.forEach(e => {
        if (e.externalTitle) set.add(e.externalTitle);
        else if (e.externalId) set.add(e.externalId);
      });
      return set.size;
    },
    monthHoursFormatted() {
      const now = new Date();
      const currentYearMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
      const mins = this.entries
        .filter(e => e.workDate && e.workDate.startsWith(currentYearMonth))
        .reduce((sum, e) => sum + (e.durationMinutes || 0), 0);
      return this.formatMinutes(mins);
    }
  },
  methods: {
    getMonday(d) {
      const date = new Date(d);
      const day = date.getDay();
      const diff = date.getDate() - day + (day === 0 ? -6 : 1);
      return new Date(date.setDate(diff));
    },
    changeWeek(offset) {
      const next = new Date(this.currentWeekMonday);
      next.setDate(next.getDate() + (offset * 7));
      this.currentWeekMonday = next;
    },
    resetToCurrentWeek() {
      this.currentWeekMonday = this.getMonday(new Date());
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const parts = dateStr.split('-');
      return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : dateStr;
    },
    formatShortDate(dateStr) {
      if (!dateStr) return '';
      const parts = dateStr.split('-');
      return parts.length === 3 ? `${parts[2]}/${parts[1]}` : dateStr;
    },
    formatMinutes(mins) {
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      return `${String(h).padStart(2, '0')}h${String(m).padStart(2, '0')}`;
    },
    getDayTotalClass(mins) {
      if (!mins || mins === 0) return '';
      if (mins >= 480) return 'ts-text-success'; // 8h ou mais
      return 'ts-text-primary';
    },
    translateStatus(st) {
      const map = {
        submitted: 'Registrado',
        approved: 'Aprovado',
        rejected: 'Rejeitado',
        draft: 'Rascunho'
      };
      return map[st] || st;
    },
    quickAddOnDate(act, dateStr) {
      const todayStr = new Date().toISOString().substring(0, 10);
      if (dateStr > todayStr) {
        alert('Não é permitido registrar apontamentos com data futura.');
        return;
      }
      this.$emit('quick-log', {
        sourceType: act.sourceType,
        externalId: act.externalId,
        externalTitle: act.title,
        workDate: dateStr,
        durationMinutes: 60,
        description: ''
      });
    },
    loadEntries() {
      this.$emit('filter-changed', {
        startDate: this.filterStartDate,
        endDate: this.filterEndDate
      });
    },
    confirmDelete(id) {
      if (confirm('Deseja realmente remover este apontamento de horas?')) {
        this.$emit('delete-entry', id);
      }
    }
  }
};
</script>
