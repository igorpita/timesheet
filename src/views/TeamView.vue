<template>
  <div class="ts-view">
    <div class="ts-view-header">
      <div>
        <h2>Gestão da Equipe de TI</h2>
        <p class="ts-subtitle">Visão consolidada do esforço realizado pelos colaboradores em projetos e demandas do Nextcloud Deck</p>
      </div>
      <div class="ts-filters">
        <select v-model="selectedUser" @change="handleUserFilter" class="ts-input ts-team-select">
          <option value="">Todos os Colaboradores</option>
          <option v-for="user in collaboratorList" :key="user" :value="user">👤 {{ user }}</option>
        </select>
        <button class="ts-btn ts-btn-sm ts-btn-outline" @click="loadTeamData">🔄 Atualizar</button>
      </div>
    </div>

    <!-- Indicadores da Equipe -->
    <div class="ts-summary-grid">
      <div class="ts-stat-card">
        <span class="ts-stat-label">Total de Horas da Equipe</span>
        <span class="ts-stat-value">{{ summary.totalHoursFormatted || '00h00' }}</span>
        <span class="ts-stat-sub">{{ totalMinutesTeam }} min registrados</span>
      </div>
      <div class="ts-stat-card">
        <span class="ts-stat-label">Demandas Atendidas</span>
        <span class="ts-stat-value">{{ activeProjectsCount }}</span>
        <span class="ts-stat-sub">Cartões com esforço apontado</span>
      </div>
      <div class="ts-stat-card">
        <span class="ts-stat-label">Total de Apontamentos</span>
        <span class="ts-stat-value">{{ filteredEntries.length }}</span>
        <span class="ts-stat-sub">Lançamentos de esforço</span>
      </div>
      <div class="ts-stat-card">
        <span class="ts-stat-label">Colaboradores Ativos</span>
        <span class="ts-stat-value">{{ activeCollaboratorsCount }}</span>
        <span class="ts-stat-sub">Membros com registros</span>
      </div>
    </div>

    <!-- Tabela Geral de Apontamentos da Equipe -->
    <div class="ts-card">
      <div class="ts-card-header">
        <div>
          <h3>Registros Consolidados da Equipe</h3>
          <span class="ts-card-subtitle" v-if="selectedUser">Filtrando por: <strong>{{ selectedUser }}</strong></span>
        </div>
        <span class="ts-badge ts-badge-deck">{{ filteredEntries.length }} registros</span>
      </div>

      <div v-if="loading" class="ts-empty-state">
        <div class="ts-spinner"></div>
        <p>Carregando dados da equipe...</p>
      </div>

      <div v-else-if="filteredEntries.length === 0" class="ts-empty-state">
        <p>Nenhum apontamento localizado para os filtros informados.</p>
      </div>

      <div v-else class="ts-table-container">
        <table class="ts-table">
          <thead>
            <tr>
              <th>Colaborador</th>
              <th>Data</th>
              <th>Origem</th>
              <th>Demanda / Cartão (Deck)</th>
              <th>Duração</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in filteredEntries" :key="entry.id">
              <td>
                <span class="ts-user-badge">👤 {{ entry.userUid }}</span>
              </td>
              <td><strong>{{ formatDate(entry.workDate) }}</strong></td>
              <td>
                <span class="ts-badge ts-badge-deck">DECK</span>
              </td>
              <td>
                <div class="ts-ref-title">{{ entry.externalTitle || ('#' + entry.externalId) }}</div>
                <small class="ts-ref-sub">{{ entry.description || 'Sem descrição' }}</small>
              </td>
              <td><span class="ts-duration-pill">{{ entry.durationFormatted }}</span></td>
              <td>
                <span class="ts-status-dot" :class="entry.status"></span>
                <span class="ts-status-label">{{ translateStatus(entry.status) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import { TimeEntryService } from '../services/api';

export default {
  name: 'TeamView',
  data() {
    return {
      entries: [],
      summary: {},
      selectedUser: '',
      loading: false,
    };
  },
  computed: {
    collaboratorList() {
      const set = new Set();
      this.entries.forEach(e => {
        if (e.userUid) set.add(e.userUid);
      });
      return Array.from(set).sort();
    },
    filteredEntries() {
      if (!this.selectedUser) return this.entries;
      return this.entries.filter(e => e.userUid === this.selectedUser);
    },
    activeCollaboratorsCount() {
      return Object.keys(this.summary.byUser || {}).length;
    },
    activeProjectsCount() {
      const set = new Set();
      this.filteredEntries.forEach(e => {
        const key = e.externalTitle || e.externalId;
        if (key) set.add(key);
      });
      return set.size;
    },
    totalMinutesTeam() {
      return this.filteredEntries.reduce((sum, e) => sum + (e.durationMinutes || 0), 0);
    }
  },
  async mounted() {
    await this.loadTeamData();
  },
  methods: {
    async loadTeamData() {
      this.loading = true;
      try {
        const [entriesRes, summaryRes] = await Promise.all([
          TimeEntryService.getTeamEntries(null, null, null),
          TimeEntryService.getTeamSummary(null, null)
        ]);
        this.entries = entriesRes.data || [];
        this.summary = summaryRes.data || summaryRes;
      } catch (e) {
        console.error('Erro ao carregar dados da equipe', e);
      } finally {
        this.loading = false;
      }
    },
    handleUserFilter() {
      // Filtragem reativa instantânea via computed filteredEntries
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const part = dateStr.substring(0, 10);
      const parts = part.split('-');
      return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : dateStr;
    },
    translateStatus(st) {
      const map = {
        submitted: 'Registrado',
        approved: 'Aprovado',
        rejected: 'Rejeitado',
        draft: 'Rascunho'
      };
      return map[st] || st;
    }
  }
};
</script>

<style scoped>
.ts-team-select {
  min-width: 220px !important;
  height: 38px !important;
  font-size: 13.5px !important;
  font-weight: 600 !important;
  padding: 6px 12px !important;
  border-radius: 8px !important;
}

.ts-status-label {
  font-weight: 600;
  font-size: 13px;
  color: #334155;
  margin-left: 4px;
}
</style>

