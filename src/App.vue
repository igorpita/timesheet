<template>
  <div class="ts-app">
    <!-- Sub-header de Navegação de Módulos (Abaixo da barra do Nextcloud) -->
    <header class="ts-topbar">
      <nav class="ts-nav">
        <button
          class="ts-nav-item"
          :class="{ active: currentTab === 'mytimesheet' }"
          @click="currentTab = 'mytimesheet'"
        >
          📋 Meu Timesheet
        </button>
        <button
          class="ts-nav-item"
          :class="{ active: currentTab === 'deck' }"
          @click="currentTab = 'deck'"
        >
          📁 Projetos (Deck)
        </button>
        <button
          v-if="isManager"
          class="ts-nav-item"
          :class="{ active: currentTab === 'team' }"
          @click="currentTab = 'team'"
        >
          👥 Equipe
        </button>
        <button
          class="ts-nav-item"
          :class="{ active: currentTab === 'reports' }"
          @click="currentTab = 'reports'"
        >
          📊 Relatórios
        </button>
      </nav>
    </header>

    <!-- Conteúdo Dinâmico das Views -->
    <main class="ts-main-content">
      <MyTimesheetView
        v-if="currentTab === 'mytimesheet'"
        :entries="myEntries"
        :loading="loadingEntries"
        @open-modal="openNewModal"
        @edit-entry="openEditModal"
        @delete-entry="handleDeleteEntry"
        @filter-changed="loadMyEntries"
        @view-audit="openAuditModal"
        @quick-log="handleQuickLog"
      />

      <DeckProjectsView
        v-else-if="currentTab === 'deck'"
        @quick-log="handleQuickLog"
      />

      <TeamView
        v-else-if="currentTab === 'team'"
      />

      <ReportsView
        v-else-if="currentTab === 'reports'"
      />
    </main>

    <!-- Modal Unificado de Apontamento -->
    <NewEntryModal
      :is-open="isModalOpen"
      :initial-data="selectedEntryForEdit"
      @close="isModalOpen = false"
      @saved="handleEntrySaved"
    />

    <!-- Modal de Trilha de Auditoria -->
    <AuditModal
      :is-open="isAuditModalOpen"
      :entry-id="selectedAuditEntryId"
      @close="isAuditModalOpen = false"
    />
  </div>
</template>

<script>
import MyTimesheetView from './views/MyTimesheetView.vue';
import DeckProjectsView from './views/DeckProjectsView.vue';
import TeamView from './views/TeamView.vue';
import ReportsView from './views/ReportsView.vue';
import NewEntryModal from './components/NewEntryModal.vue';
import AuditModal from './components/AuditModal.vue';
import { TimeEntryService } from './services/api';

export default {
  name: 'App',
  components: {
    MyTimesheetView,
    DeckProjectsView,
    TeamView,
    ReportsView,
    NewEntryModal,
    AuditModal
  },
  data() {
    // Lê dados iniciais do container Nextcloud
    const rootEl = document.getElementById('oab-timesheet-app');
    const userUid = rootEl?.dataset?.userUid || 'igor.pita';
    const userName = rootEl?.dataset?.userName || 'Igor Pita';
    const isManager = rootEl?.dataset?.isManager === 'true';

    return {
      currentTab: 'mytimesheet',
      userUid,
      userName,
      isManager,
      myEntries: [],
      loadingEntries: false,
      isModalOpen: false,
      selectedEntryForEdit: null,
      isAuditModalOpen: false,
      selectedAuditEntryId: null
    };
  },
  computed: {
    userInitials() {
      if (!this.userName) return 'TI';
      const parts = this.userName.trim().split(' ');
      if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
      }
      return this.userName.substring(0, 2).toUpperCase();
    }
  },
  async mounted() {
    await this.loadMyEntries();
  },
  methods: {
    async loadMyEntries(filters = {}) {
      this.loadingEntries = true;
      try {
        const res = await TimeEntryService.getMyEntries(filters.startDate, filters.endDate);
        this.myEntries = res.data || [];
      } catch (err) {
        console.error('Erro ao buscar apontamentos', err);
      } finally {
        this.loadingEntries = false;
      }
    },
    openNewModal() {
      this.selectedEntryForEdit = null;
      this.isModalOpen = true;
    },
    openAuditModal(id) {
      this.selectedAuditEntryId = id;
      this.isAuditModalOpen = true;
    },
    openEditModal(entry) {
      this.selectedEntryForEdit = entry;
      this.isModalOpen = true;
    },
    handleQuickLog(presetData) {
      this.selectedEntryForEdit = presetData;
      this.isModalOpen = true;
    },
    async handleEntrySaved(entryData) {
      try {
        if (entryData.id) {
          await TimeEntryService.updateEntry(entryData.id, entryData);
        } else {
          await TimeEntryService.createEntry(entryData);
        }
        await this.loadMyEntries();
        this.currentTab = 'mytimesheet';
      } catch (err) {
        alert('Erro ao salvar apontamento: ' + (err.response?.data?.error || err.message));
      }
    },
    async handleDeleteEntry(id) {
      try {
        await TimeEntryService.deleteEntry(id);
        await this.loadMyEntries();
      } catch (err) {
        alert('Erro ao excluir apontamento: ' + (err.response?.data?.error || err.message));
      }
    }
  }
};
</script>

