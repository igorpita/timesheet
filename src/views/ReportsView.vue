<template>
  <div class="ts-view ts-reports-view">
    <!-- Header do Módulo de Relatórios -->
    <div class="ts-view-header">
      <div>
        <h2>Relatórios & Indicadores de Projetos (Deck)</h2>
        <p class="ts-subtitle">Consolidação analítica e sumarizada de esforço dedicado às demandas do Nextcloud Deck</p>
      </div>
      <div class="ts-header-actions no-print">
        <button class="ts-btn ts-btn-outline" @click="exportToCsv" title="Exportar dados filtrados em formato CSV">
          📥 Exportar CSV
        </button>
        <button class="ts-btn ts-btn-outline" @click="printReport" title="Imprimir ou salvar em PDF">
          🖨️ Imprimir
        </button>
        <a :href="metabaseDirectUrl" target="_blank" rel="noopener noreferrer" class="ts-btn ts-btn-primary" title="Abrir painel com dashboards avançados no Metabase">
          📊 Abrir Metabase BI ↗
        </a>
      </div>
    </div>

    <!-- Barra de Filtros Avançados -->
    <div class="ts-card ts-filter-card no-print">
      <div class="ts-filters-grid">
        <div class="ts-filter-group ts-filter-date">
          <label class="ts-filter-label">Data Inicial</label>
          <input type="date" v-model="filterStartDate" @change="fetchReports" class="ts-input ts-report-input ts-date-input" />
        </div>

        <div class="ts-filter-group ts-filter-date">
          <label class="ts-filter-label">Data Final</label>
          <input type="date" v-model="filterEndDate" @change="fetchReports" class="ts-input ts-report-input ts-date-input" />
        </div>

        <div class="ts-filter-group ts-filter-select">
          <label class="ts-filter-label">Filtrar por Projeto</label>
          <select v-model="selectedProject" class="ts-select ts-report-input">
            <option value="">📁 Todos os Projetos (Deck)</option>
            <option v-for="proj in availableProjects" :key="proj" :value="proj">
              {{ proj }}
            </option>
          </select>
        </div>

        <div class="ts-filter-group ts-filter-select">
          <label class="ts-filter-label">Filtrar por Colaborador</label>
          <select v-model="selectedCollaborator" class="ts-select ts-report-input">
            <option value="">👥 Todos os Colaboradores</option>
            <option v-for="user in availableCollaborators" :key="user" :value="user">
              {{ user }}
            </option>
          </select>
        </div>

        <div class="ts-filter-group ts-filter-search">
          <label class="ts-filter-label">Pesquisa Rápida</label>
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Pesquisar por tarefa ou descrição..."
            class="ts-input ts-report-input ts-search-input"
          />
        </div>
      </div>

      <!-- Atalhos de Período -->
      <div class="ts-filter-shortcuts">
        <span class="ts-shortcuts-label">Atalhos:</span>
        <button type="button" class="ts-shortcut-pill" @click="setPeriod('this-month')">Mês Atual</button>
        <button type="button" class="ts-shortcut-pill" @click="setPeriod('last-month')">Mês Anterior</button>
        <button type="button" class="ts-shortcut-pill" @click="setPeriod('last-30')">Últimos 30 Dias</button>
        <button type="button" class="ts-shortcut-pill" @click="setPeriod('all')">Todo o Histórico</button>
      </div>
    </div>

    <!-- Indicadores / KPIs em Destaque -->
    <div class="ts-summary-grid">
      <div class="ts-stat-card">
        <span class="ts-stat-label">Total de Horas</span>
        <span class="ts-stat-value">{{ filteredTotalHoursFormatted }}</span>
        <span class="ts-stat-sub">{{ filteredMinutes }} minutos acumulados</span>
      </div>

      <div class="ts-stat-card">
        <span class="ts-stat-label">Projetos com Esforço</span>
        <span class="ts-stat-value">{{ summarizedByProject.length }}</span>
        <span class="ts-stat-sub">Quadros Deck ativos</span>
      </div>

      <div class="ts-stat-card">
        <span class="ts-stat-label">Colaboradores Ativos</span>
        <span class="ts-stat-value">{{ summarizedByCollaborator.length }}</span>
        <span class="ts-stat-sub">Membros com registros</span>
      </div>

      <div class="ts-stat-card">
        <span class="ts-stat-label">Total de Apontamentos</span>
        <span class="ts-stat-value">{{ filteredEntries.length }}</span>
        <span class="ts-stat-sub">Lançamentos no período</span>
      </div>
    </div>

    <!-- Abas / Modos de Visualização dos Relatórios -->
    <div class="ts-report-tabs-nav no-print">
      <button
        class="ts-tab-btn"
        :class="{ active: activeReportTab === 'by-project' }"
        @click="activeReportTab = 'by-project'"
      >
        📁 Resumo por Projeto
      </button>
      <button
        class="ts-tab-btn"
        :class="{ active: activeReportTab === 'by-collaborator' }"
        @click="activeReportTab = 'by-collaborator'"
      >
        👥 Resumo por Colaborador
      </button>
      <button
        class="ts-tab-btn"
        :class="{ active: activeReportTab === 'analytical' }"
        @click="activeReportTab = 'analytical'"
      >
        📋 Relatório Analítico Detalhado ({{ filteredEntries.length }})
      </button>
    </div>

    <!-- ESTADO DE CARREGAMENTO -->
    <div v-if="loading" class="ts-card ts-empty-state">
      <div class="ts-spinner"></div>
      <p>Consolidando indicadores analíticos...</p>
    </div>

    <!-- SEM RESULTADOS -->
    <div v-else-if="filteredEntries.length === 0" class="ts-card ts-empty-state">
      <p>Nenhum registro encontrado para o filtro e período selecionados.</p>
      <button class="ts-btn ts-btn-outline ts-btn-sm" @click="resetFilters">
        Limpar Filtros
      </button>
    </div>

    <!-- TAB 1: RESUMO POR PROJETO (DECK) -->
    <div v-else-if="activeReportTab === 'by-project'" class="ts-card">
      <div class="ts-card-header">
        <div>
          <h3>Distribuição de Esforço por Projeto (Deck)</h3>
          <span class="ts-card-subtitle">Horas consolidadas, tarefas atendidas e percentual de dedicação</span>
        </div>
      </div>
      <div class="ts-table-container">
        <table class="ts-table ts-report-table">
          <thead>
            <tr>
              <th style="width: 35%;">Projeto / Quadro</th>
              <th style="width: 15%; text-align: center;">Tarefas Atendidas</th>
              <th style="width: 15%; text-align: center;">Apontamentos</th>
              <th style="width: 15%; text-align: right;">Total de Horas</th>
              <th style="width: 20%;">% do Esforço Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="proj in summarizedByProject" :key="proj.projectName">
              <td>
                <div class="ts-proj-name">
                  <span class="ts-proj-icon">📁</span>
                  <strong>{{ proj.projectName }}</strong>
                </div>
              </td>
              <td style="text-align: center;">
                <span class="ts-count-badge">{{ proj.uniqueCardsCount }} tarefas</span>
              </td>
              <td style="text-align: center;">
                {{ proj.count }}
              </td>
              <td style="text-align: right;">
                <strong class="ts-hours-highlight">{{ proj.totalHoursFormatted }}</strong>
              </td>
              <td>
                <div class="ts-progress-box">
                  <div class="ts-progress-bar-bg">
                    <div class="ts-progress-bar-fill" :style="{ width: proj.percentage + '%' }"></div>
                  </div>
                  <span class="ts-progress-text">{{ proj.percentage }}%</span>
                </div>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="ts-table-total-row">
              <td><strong>Total Geral Consolidado</strong></td>
              <td style="text-align: center;"><strong>{{ totalUniqueCardsInFiltered }} tarefas</strong></td>
              <td style="text-align: center;"><strong>{{ filteredEntries.length }}</strong></td>
              <td style="text-align: right;"><strong>{{ filteredTotalHoursFormatted }}</strong></td>
              <td><strong>100.0%</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- TAB 2: RESUMO POR COLABORADOR -->
    <div v-else-if="activeReportTab === 'by-collaborator'" class="ts-card">
      <div class="ts-card-header">
        <div>
          <h3>Carga e Distribuição por Colaborador</h3>
          <span class="ts-card-subtitle">Dias produtivos, lançamentos efetuados, horas totais e média diária</span>
        </div>
      </div>
      <div class="ts-table-container">
        <table class="ts-table ts-report-table">
          <thead>
            <tr>
              <th style="width: 30%;">Colaborador / Técnico</th>
              <th style="width: 15%; text-align: center;">Dias c/ Registro</th>
              <th style="width: 15%; text-align: center;">Apontamentos</th>
              <th style="width: 15%; text-align: right;">Total de Horas</th>
              <th style="width: 12%; text-align: right;">Média / Dia</th>
              <th style="width: 13%;">% do Esforço</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in summarizedByCollaborator" :key="user.userUid">
              <td>
                <div class="ts-user-col-info">
                  <span class="ts-avatar-small">{{ user.userUid.substring(0, 2).toUpperCase() }}</span>
                  <strong>{{ user.userUid }}</strong>
                </div>
              </td>
              <td style="text-align: center;">
                <span class="ts-count-badge">{{ user.daysWorked }} dias</span>
              </td>
              <td style="text-align: center;">
                {{ user.count }}
              </td>
              <td style="text-align: right;">
                <strong class="ts-hours-highlight">{{ user.totalHoursFormatted }}</strong>
              </td>
              <td style="text-align: right; color: #475569;">
                {{ user.dailyAvgFormatted }}
              </td>
              <td>
                <div class="ts-progress-box">
                  <div class="ts-progress-bar-bg">
                    <div class="ts-progress-bar-fill ts-progress-blue" :style="{ width: user.percentage + '%' }"></div>
                  </div>
                  <span class="ts-progress-text">{{ user.percentage }}%</span>
                </div>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="ts-table-total-row">
              <td><strong>Total da Equipe</strong></td>
              <td style="text-align: center;"><strong>{{ totalUniqueDaysInFiltered }} dias únicos</strong></td>
              <td style="text-align: center;"><strong>{{ filteredEntries.length }}</strong></td>
              <td style="text-align: right;"><strong>{{ filteredTotalHoursFormatted }}</strong></td>
              <td style="text-align: right;">-</td>
              <td><strong>100.0%</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- TAB 3: RELATÓRIO ANALÍTICO DETALHADO -->
    <div v-else-if="activeReportTab === 'analytical'" class="ts-card">
      <div class="ts-card-header">
        <div>
          <h3>Relatório Analítico Detalhado de Apontamentos</h3>
          <span class="ts-card-subtitle">Relação completa de todos os lançamentos com projeto, tarefa e descrição</span>
        </div>
        <span class="ts-badge ts-badge-deck">{{ filteredEntries.length }} registros exibidos</span>
      </div>
      <div class="ts-table-container">
        <table class="ts-table ts-report-table">
          <thead>
            <tr>
              <th style="width: 10%;">Data</th>
              <th style="width: 12%;">Colaborador</th>
              <th style="width: 20%;">Projeto (Deck)</th>
              <th style="width: 23%;">Tarefa / Cartão</th>
              <th style="width: 25%;">Descrição do Trabalho</th>
              <th style="width: 10%; text-align: right;">Duração</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in filteredEntries" :key="entry.id">
              <td>
                <strong>{{ formatDate(entry.workDate) }}</strong>
              </td>
              <td>
                <span class="ts-user-pill">{{ entry.userUid }}</span>
              </td>
              <td>
                <span class="ts-project-pill">📁 {{ entry.projectName }}</span>
              </td>
              <td>
                <div class="ts-task-detail-title">
                  <span class="ts-card-id-tag">#{{ entry.externalId }}</span>
                  {{ entry.cardTitle }}
                </div>
              </td>
              <td class="ts-analytical-desc">
                {{ entry.description }}
              </td>
              <td style="text-align: right;">
                <span class="ts-duration-pill">{{ entry.durationFormatted }}</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="ts-table-total-row">
              <td colspan="5"><strong>Total Geral de Horas Apontadas</strong></td>
              <td style="text-align: right;"><strong>{{ filteredTotalHoursFormatted }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import { TimeEntryService } from '../services/api';

export default {
  name: 'ReportsView',
  data() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    return {
      loading: true,
      rawEntries: [],
      availableProjects: [],
      availableCollaborators: [],
      
      // Filtros
      filterStartDate: this.formatDateInput(firstDay),
      filterEndDate: this.formatDateInput(today),
      selectedProject: '',
      selectedCollaborator: '',
      searchQuery: '',
      
      activeReportTab: 'by-project',
      metabaseDirectUrl: 'http://localhost:3000/dashboard/2'
    };
  },
  computed: {
    filteredEntries() {
      return this.rawEntries.filter((entry) => {
        // Filtro por projeto
        if (this.selectedProject && entry.projectName !== this.selectedProject) {
          return false;
        }

        // Filtro por colaborador
        if (this.selectedCollaborator && entry.userUid !== this.selectedCollaborator) {
          return false;
        }

        // Filtro por termo de busca
        if (this.searchQuery && this.searchQuery.trim() !== '') {
          const q = this.searchQuery.toLowerCase();
          const inDesc = (entry.description || '').toLowerCase().includes(q);
          const inCard = (entry.cardTitle || '').toLowerCase().includes(q);
          const inProj = (entry.projectName || '').toLowerCase().includes(q);
          const inUser = (entry.userUid || '').toLowerCase().includes(q);
          if (!inDesc && !inCard && !inProj && !inUser) {
            return false;
          }
        }

        return true;
      });
    },

    filteredMinutes() {
      return this.filteredEntries.reduce((sum, e) => sum + (e.durationMinutes || 0), 0);
    },

    filteredTotalHoursFormatted() {
      const mins = this.filteredMinutes;
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      return `${String(h).padStart(2, '0')}h${String(m).padStart(2, '0')}`;
    },

    summarizedByProject() {
      const total = this.filteredMinutes;
      const map = {};

      for (const e of this.filteredEntries) {
        const pName = e.projectName || 'Projeto Geral';
        if (!map[pName]) {
          map[pName] = {
            projectName: pName,
            totalMinutes: 0,
            count: 0,
            cardsMap: {}
          };
        }
        map[pName].totalMinutes += (e.durationMinutes || 0);
        map[pName].count += 1;
        map[pName].cardsMap[e.externalId] = true;
      }

      const list = Object.values(map).map((p) => {
        const h = Math.floor(p.totalMinutes / 60);
        const m = p.totalMinutes % 60;
        const pct = total > 0 ? ((p.totalMinutes / total) * 100).toFixed(1) : 0;
        return {
          projectName: p.projectName,
          uniqueCardsCount: Object.keys(p.cardsMap).length,
          count: p.count,
          totalMinutes: p.totalMinutes,
          totalHoursFormatted: `${String(h).padStart(2, '0')}h${String(m).padStart(2, '0')}`,
          percentage: Number(pct)
        };
      });

      return list.sort((a, b) => b.totalMinutes - a.totalMinutes);
    },

    summarizedByCollaborator() {
      const total = this.filteredMinutes;
      const map = {};

      for (const e of this.filteredEntries) {
        const u = e.userUid || 'anônimo';
        if (!map[u]) {
          map[u] = {
            userUid: u,
            totalMinutes: 0,
            count: 0,
            daysMap: {}
          };
        }
        map[u].totalMinutes += (e.durationMinutes || 0);
        map[u].count += 1;
        map[u].daysMap[e.workDate] = true;
      }

      const list = Object.values(map).map((u) => {
        const h = Math.floor(u.totalMinutes / 60);
        const m = u.totalMinutes % 60;
        const days = Math.max(1, Object.keys(u.daysMap).length);
        const avg = Math.round(u.totalMinutes / days);
        const avgH = Math.floor(avg / 60);
        const avgM = avg % 60;
        const pct = total > 0 ? ((u.totalMinutes / total) * 100).toFixed(1) : 0;

        return {
          userUid: u.userUid,
          daysWorked: Object.keys(u.daysMap).length,
          count: u.count,
          totalMinutes: u.totalMinutes,
          totalHoursFormatted: `${String(h).padStart(2, '0')}h${String(m).padStart(2, '0')}`,
          dailyAvgFormatted: `${String(avgH).padStart(2, '0')}h${String(avgM).padStart(2, '0')}`,
          percentage: Number(pct)
        };
      });

      return list.sort((a, b) => b.totalMinutes - a.totalMinutes);
    },

    totalUniqueCardsInFiltered() {
      const set = new Set();
      this.filteredEntries.forEach(e => set.add(e.externalId));
      return set.size;
    },

    totalUniqueDaysInFiltered() {
      const set = new Set();
      this.filteredEntries.forEach(e => set.add(e.workDate));
      return set.size;
    }
  },
  mounted() {
    this.fetchReports();
  },
  methods: {
    formatDateInput(d) {
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },

    formatDate(dateStr) {
      if (!dateStr) return '';
      const [year, month, day] = dateStr.split('-');
      return `${day}/${month}/${year}`;
    },

    setPeriod(type) {
      const now = new Date();
      if (type === 'this-month') {
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        this.filterStartDate = this.formatDateInput(first);
        this.filterEndDate = this.formatDateInput(now);
      } else if (type === 'last-month') {
        const first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const last = new Date(now.getFullYear(), now.getMonth(), 0);
        this.filterStartDate = this.formatDateInput(first);
        this.filterEndDate = this.formatDateInput(last);
      } else if (type === 'last-30') {
        const past = new Date(now.getTime() - (30 * 24 * 60 * 60 * 1000));
        this.filterStartDate = this.formatDateInput(past);
        this.filterEndDate = this.formatDateInput(now);
      } else if (type === 'all') {
        this.filterStartDate = '';
        this.filterEndDate = '';
      }
      this.fetchReports();
    },

    resetFilters() {
      this.selectedProject = '';
      this.selectedCollaborator = '';
      this.searchQuery = '';
      this.setPeriod('this-month');
    },

    async fetchReports() {
      this.loading = true;
      try {
        const res = await TimeEntryService.getReports(this.filterStartDate, this.filterEndDate);
        if (res.success && res.data) {
          this.rawEntries = res.data.entries || [];
          this.availableProjects = res.data.projects || [];
          this.availableCollaborators = res.data.collaborators || [];
        }
      } catch (err) {
        console.error('Erro ao carregar dados dos relatórios:', err);
      } finally {
        this.loading = false;
      }
    },

    exportToCsv() {
      if (this.filteredEntries.length === 0) {
        alert('Não há dados para exportar no período selecionado.');
        return;
      }

      const headers = ['Data', 'Colaborador', 'Projeto', 'Cartao_ID', 'Tarefa_Cartao', 'Descricao', 'Duracao_Minutos', 'Duracao_Formatada'];
      const rows = this.filteredEntries.map(e => [
        e.workDate,
        `"${(e.userUid || '').replace(/"/g, '""')}"`,
        `"${(e.projectName || '').replace(/"/g, '""')}"`,
        e.externalId,
        `"${(e.cardTitle || '').replace(/"/g, '""')}"`,
        `"${(e.description || '').replace(/"/g, '""')}"`,
        e.durationMinutes,
        e.durationFormatted
      ]);

      const csvContent = '\uFEFF' + [headers.join(';'), ...rows.map(r => r.join(';'))].join('\r\n');
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `relatorio_timesheet_${this.filterStartDate || 'inicio'}_${this.filterEndDate || 'fim'}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    },

    printReport() {
      window.print();
    }
  }
};
</script>

<style scoped>
.ts-reports-view {
  color: #0f172a;
}

/* Barra de Filtros */
.ts-filter-card {
  padding: 16px 20px;
  margin-bottom: 24px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.ts-filters-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-end;
}

.ts-filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ts-filter-date {
  flex: 0 0 175px;
  min-width: 175px;
}

.ts-filter-select {
  flex: 1 1 220px;
  min-width: 200px;
}

.ts-filter-search {
  flex: 2 1 280px;
  min-width: 250px;
}

.ts-report-input {
  height: 42px !important;
  font-size: 14px !important;
  padding: 8px 14px !important;
  box-sizing: border-box !important;
  border-radius: 8px !important;
  border: 1px solid #cbd5e1 !important;
  background-color: #ffffff !important;
  color: #0f172a !important;
}

.ts-date-input {
  font-weight: 600 !important;
  letter-spacing: 0.2px !important;
}

.ts-search-input {
  width: 100% !important;
}

.ts-filter-label {
  font-size: 12px;
  font-weight: 700;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.ts-filter-shortcuts {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.ts-shortcuts-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
}

.ts-shortcut-pill {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 9999px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
}

.ts-shortcut-pill:hover {
  background: #e2e8f0;
  color: #0f172a;
}

/* Abas de Relatório */
.ts-report-tabs-nav {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 2px;
}

.ts-tab-btn {
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 700;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-bottom: -4px;
}

.ts-tab-btn:hover {
  color: #0f172a;
}

.ts-tab-btn.active {
  color: #0082c9;
  border-bottom-color: #0082c9;
}

/* Tabelas de Relatório */
.ts-report-table {
  width: 100%;
  border-collapse: collapse;
}

.ts-report-table th {
  background: #f8fafc;
  color: #334155;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 12px 16px;
  border-bottom: 2px solid #e2e8f0;
}

.ts-report-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
  color: #1e293b;
  vertical-align: middle;
}

.ts-report-table tbody tr:hover {
  background: #f8fafc;
}

.ts-table-total-row td {
  background: #f8fafc;
  border-top: 2px solid #cbd5e1;
  border-bottom: none;
  font-size: 14px;
  color: #0f172a;
}

/* Elementos Visuais das Tabelas */
.ts-proj-name {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ts-proj-icon {
  font-size: 16px;
}

.ts-count-badge {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 3px 8px;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
}

.ts-hours-highlight {
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
}

.ts-progress-box {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ts-progress-bar-bg {
  flex: 1;
  height: 8px;
  background: #e2e8f0;
  border-radius: 9999px;
  overflow: hidden;
}

.ts-progress-bar-fill {
  height: 100%;
  background: #16a34a;
  border-radius: 9999px;
  transition: width 0.3s ease;
}

.ts-progress-blue {
  background: #0284c7;
}

.ts-progress-text {
  font-size: 12px;
  font-weight: 700;
  color: #334155;
  width: 44px;
  text-align: right;
}

.ts-user-col-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ts-avatar-small {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #0082c9;
  color: #ffffff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
}

.ts-user-pill {
  background: #e0f2fe;
  color: #0369a1;
  border-radius: 6px;
  padding: 3px 8px;
  font-size: 12px;
  font-weight: 700;
}

.ts-project-pill {
  font-weight: 600;
  color: #1e293b;
}

.ts-card-id-tag {
  color: #0284c7;
  font-weight: 700;
  margin-right: 4px;
}

.ts-task-detail-title {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
}

.ts-analytical-desc {
  color: #475569;
  line-height: 1.4;
}

/* Suporte de Impressão */
@media print {
  .no-print {
    display: none !important;
  }

  .ts-view {
    padding: 0 !important;
  }

  .ts-card {
    border: none !important;
    box-shadow: none !important;
  }
}
</style>
