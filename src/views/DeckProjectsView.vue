<template>
  <div class="ts-view">
    <div class="ts-view-header">
      <div>
        <h2>Projetos — Nextcloud Deck</h2>
        <p class="ts-subtitle">Consulte os quadros e cartões aos quais você possui permissão e aponte horas diretamente</p>
      </div>
    </div>

    <!-- Barra de Controle: Seletor de Quadro & Filtros de Cartão -->
    <div class="ts-card ts-deck-toolbar-card">
      <div class="ts-deck-toolbar-grid">
        <!-- 1. Seletor de Quadro / Projeto -->
        <div class="ts-toolbar-group ts-toolbar-board">
          <label class="ts-toolbar-label">
            📁 Quadro / Projeto
            <span class="ts-count-tag" v-if="boards.length > 0">{{ filteredBoards.length }} de {{ boards.length }}</span>
          </label>
          <div class="ts-board-input-row">
            <select
              v-model="selectedBoardId"
              @change="onBoardSelectChange"
              class="ts-select ts-board-select"
            >
              <option v-if="filteredBoards.length === 0" disabled value="">
                Nenhum quadro encontrado
              </option>
              <option
                v-for="board in filteredBoards"
                :key="board.id"
                :value="board.id"
              >
                {{ board.title }}
              </option>
            </select>
            <input
              type="text"
              v-model="boardSearchText"
              @input="onBoardFilterInput"
              placeholder="🔍 Filtrar quadro..."
              class="ts-input ts-board-filter-input"
              title="Digite para filtrar os quadros disponíveis"
            />
          </div>
        </div>

        <!-- 2. Busca e Filtro de Cartões -->
        <div class="ts-toolbar-group ts-toolbar-cards">
          <label class="ts-toolbar-label">🔎 Filtrar Cartões</label>
          <div class="ts-cards-filter-row">
            <input
              type="text"
              v-model="searchQuery"
              @input="handleSearch"
              placeholder="Pesquisar cartão por título..."
              class="ts-input ts-search-deck-input"
            />
            <button
              type="button"
              class="ts-filter-assigned-btn"
              :class="{ active: onlyAssignedToMe }"
              @click="onlyAssignedToMe = !onlyAssignedToMe"
              title="Exibir somente cartões onde você está atribuído"
            >
              <span class="ts-filter-icon">{{ onlyAssignedToMe ? '☑️' : '◻️' }}</span>
              <span>Atribuídos a mim</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cartões do Quadro Selecionado -->
    <div class="ts-card">
      <div class="ts-card-header">
        <div>
          <h3>Cartões disponíveis para apontamento</h3>
          <span class="ts-card-subtitle" v-if="selectedBoard">Quadro: <strong>{{ selectedBoard.title }}</strong></span>
        </div>
        <div class="ts-header-badges">
          <span v-if="onlyAssignedToMe" class="ts-badge ts-badge-filtered">Filtro: Atribuídos a mim</span>
          <span class="ts-badge ts-badge-deck">{{ filteredCards.length }} {{ filteredCards.length === 1 ? 'cartão' : 'cartões' }}</span>
        </div>
      </div>

      <div v-if="loadingCards" class="ts-empty-state">
        <div class="ts-spinner"></div>
        <p>Carregando cartões do Deck...</p>
      </div>

      <div v-else-if="filteredCards.length === 0" class="ts-empty-state">
        <p v-if="onlyAssignedToMe">Nenhum cartão atribuído a você encontrado neste quadro.</p>
        <p v-else>Nenhum cartão encontrado neste quadro.</p>
      </div>

      <div v-else class="ts-card-list">
        <!-- Cabeçalho das Colunas do Grid de Cartões -->
        <div class="ts-deck-table-header">
          <div class="ts-col-th ts-th-task">Cartão / Demanda</div>
          <div class="ts-col-th ts-th-dates">Cronograma (Início / Prazo)</div>
          <div class="ts-col-th ts-th-effort">Horas Apontadas</div>
          <div class="ts-col-th ts-th-actions">Ações</div>
        </div>

        <div v-for="card in filteredCards" :key="card.id" class="ts-deck-task-row">
          <!-- Coluna 1: Tarefa, ID, Quadro e Tags -->
          <div class="ts-deck-col-task">
            <div class="ts-deck-task-title-wrap">
              <span class="ts-deck-task-id">#{{ card.id }}</span>
              <strong class="ts-deck-task-name">{{ card.title }}</strong>
              <span v-if="card.isDone" class="ts-deck-done-badge">✓ Concluído</span>
            </div>
            <div class="ts-deck-task-pills">
              <span class="ts-stack-pill">📁 {{ card.stack }}</span>
              <span v-if="card.assigned" class="ts-assigned-pill">👤 Atribuído a você</span>
            </div>
          </div>

          <!-- Coluna 2: Cronograma (Data Início e Prazo) -->
          <div class="ts-deck-col-dates">
            <div class="ts-date-field">
              <span class="ts-date-field-label">Início:</span>
              <span v-if="card.startDate" class="ts-date-field-val">
                📅 {{ formatDate(card.startDate) }}
              </span>
              <span v-else class="ts-date-field-val ts-text-muted">
                📅 Não definido
              </span>
            </div>
            <div class="ts-date-field">
              <span class="ts-date-field-label">Prazo:</span>
              <span v-if="card.dueDate" class="ts-date-field-val" :class="getDueClass(card.dueDate)">
                ⏰ {{ formatDate(card.dueDate) }}
                <span v-if="isOverdue(card.dueDate)" class="ts-due-tag overdue">Vencido</span>
                <span v-else-if="isDueSoon(card.dueDate)" class="ts-due-tag soon">Vence logo</span>
              </span>
              <span v-else class="ts-date-field-val ts-text-muted">
                ⏰ Sem prazo
              </span>
            </div>
          </div>

          <!-- Coluna 3: Horas Apontadas -->
          <div class="ts-deck-col-effort">
            <div class="ts-effort-pill" :class="{ 'has-hours': card.totalMinutes > 0 }">
              <span class="ts-effort-icon">⏱️</span>
              <span class="ts-effort-time">{{ card.totalHoursFormatted || '00h00' }}</span>
            </div>
            <span class="ts-effort-caption">
              {{ card.totalMinutes > 0 ? (card.totalMinutes + ' min acumulados') : 'Sem registros' }}
            </span>
          </div>

          <!-- Coluna 4: Ações -->
          <div class="ts-deck-col-actions">
            <button class="ts-btn-deck ts-btn-deck-hist" title="Ver histórico de apontamentos" @click="openHistory(card)">
              📜 Histórico
            </button>
            <button class="ts-btn-deck ts-btn-deck-log" title="Apontar horas neste cartão" @click="logTimeForCard(card)">
              ➕ Apontar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Histórico do Cartão -->
    <div v-if="historyModalOpen" class="ts-modal-overlay" @click.self="historyModalOpen = false">
      <div class="ts-modal-card">
        <div class="ts-modal-header">
          <h3>Histórico de Horas — Cartão #{{ activeCard?.id }}</h3>
          <button class="ts-btn-close" @click="historyModalOpen = false">&times;</button>
        </div>
        <div class="ts-modal-body">
          <p><strong>{{ activeCard?.title }}</strong></p>
          <div class="ts-stat-banner">
            Total acumulado no cartão: <strong>{{ activeCardTotalHours }}</strong>
          </div>
          <div v-if="cardEntries.length === 0" class="ts-empty-state">
            <p>Ainda não há apontamentos registrados para este cartão.</p>
          </div>
          <ul v-else class="ts-history-list">
            <li v-for="item in cardEntries" :key="item.id" class="ts-history-item">
              <div>
                <strong>{{ item.userUid }}</strong> em {{ formatDate(item.workDate) }}
                <p>{{ item.description }}</p>
              </div>
              <span class="ts-duration-pill">{{ item.durationFormatted }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { TimeEntryService } from '../services/api';

export default {
  name: 'DeckProjectsView',
  emits: ['quick-log'],
  data() {
    return {
      boards: [],
      cards: [],
      selectedBoardId: null,
      boardSearchText: '',
      onlyAssignedToMe: false,
      loadingCards: false,
      searchQuery: '',
      historyModalOpen: false,
      activeCard: null,
      activeCardTotalHours: '0h00',
      cardEntries: []
    };
  },
  computed: {
    selectedBoard() {
      return this.boards.find(b => b.id === this.selectedBoardId) || null;
    },
    filteredBoards() {
      const q = (this.boardSearchText || '').trim().toLowerCase();
      const sorted = [...this.boards].sort((a, b) =>
        (a.title || '').localeCompare(b.title || '', 'pt-BR', { sensitivity: 'base' })
      );
      if (!q) return sorted;
      return sorted.filter(b => (b.title || '').toLowerCase().includes(q));
    },
    filteredCards() {
      if (!this.onlyAssignedToMe) {
        return this.cards;
      }
      return this.cards.filter(c => c.assigned === true);
    }
  },
  async mounted() {
    await this.loadBoards();
  },
  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      const part = dateStr.substring(0, 10);
      const [year, month, day] = part.split('-');
      return `${day}/${month}/${year}`;
    },

    isOverdue(dateStr) {
      if (!dateStr) return false;
      const due = new Date(dateStr.substring(0, 10)).getTime();
      const today = new Date(new Date().toISOString().substring(0, 10)).getTime();
      return due < today;
    },

    isDueSoon(dateStr) {
      if (!dateStr) return false;
      const due = new Date(dateStr.substring(0, 10)).getTime();
      const today = new Date(new Date().toISOString().substring(0, 10)).getTime();
      const diffDays = (due - today) / (1000 * 3600 * 24);
      return diffDays >= 0 && diffDays <= 3;
    },

    getDueClass(dateStr) {
      if (!dateStr) return '';
      if (this.isOverdue(dateStr)) return 'ts-due-overdue';
      if (this.isDueSoon(dateStr)) return 'ts-due-soon';
      return '';
    },

    async loadBoards() {
      try {
        const res = await TimeEntryService.getDeckBoards();
        this.boards = res.data || [];
        if (this.boards.length > 0) {
          const sorted = [...this.boards].sort((a, b) =>
            (a.title || '').localeCompare(b.title || '', 'pt-BR', { sensitivity: 'base' })
          );
          this.selectBoard(sorted[0].id);
        }
      } catch (e) {
        console.error('Erro ao carregar boards', e);
      }
    },

    onBoardSelectChange() {
      if (this.selectedBoardId) {
        this.selectBoard(this.selectedBoardId);
      }
    },

    onBoardFilterInput() {
      if (this.filteredBoards.length > 0) {
        const found = this.filteredBoards.some(b => b.id === this.selectedBoardId);
        if (!found) {
          this.selectBoard(this.filteredBoards[0].id);
        }
      }
    },

    async selectBoard(boardId) {
      this.selectedBoardId = boardId;
      this.loadingCards = true;
      try {
        const res = await TimeEntryService.getDeckCards(boardId);
        this.cards = res.data || [];
      } catch (e) {
        console.error('Erro ao carregar cards', e);
      } finally {
        this.loadingCards = false;
      }
    },

    async handleSearch() {
      if (!this.searchQuery) {
        if (this.selectedBoardId) {
          this.selectBoard(this.selectedBoardId);
        }
        return;
      }
      this.loadingCards = true;
      try {
        const res = await TimeEntryService.searchDeckCards(this.searchQuery);
        this.cards = res.data || [];
      } catch (e) {
        console.error('Erro ao pesquisar cards', e);
      } finally {
        this.loadingCards = false;
      }
    },

    logTimeForCard(card) {
      this.$emit('quick-log', {
        sourceType: 'DECK',
        externalId: String(card.id),
        externalTitle: card.title,
        workDate: new Date().toISOString().substring(0, 10),
        durationMinutes: 60,
        description: ''
      });
    },

    async openHistory(card) {
      this.activeCard = card;
      this.historyModalOpen = true;
      try {
        const res = await TimeEntryService.getDeckCardHistory(card.id);
        this.cardEntries = res.entries || [];
        this.activeCardTotalHours = res.totalFormatted || '0h00';
      } catch (e) {
        this.cardEntries = [];
        this.activeCardTotalHours = '0h00';
      }
    }
  }
};
</script>

<style scoped>
/* Toolbar de Controle de Projetos e Cartões */
.ts-deck-toolbar-card {
  padding: 16px 20px;
  margin-bottom: 20px;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.ts-deck-toolbar-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  align-items: flex-end;
}

.ts-toolbar-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ts-toolbar-board {
  flex: 1.5 1 340px;
}

.ts-toolbar-cards {
  flex: 1.8 1 380px;
}

.ts-toolbar-label {
  font-size: 12px;
  font-weight: 700;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.ts-count-tag {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  background-color: #f1f5f9;
  padding: 2px 8px;
  border-radius: 9999px;
  text-transform: none;
}

.ts-board-input-row,
.ts-cards-filter-row {
  display: flex;
  gap: 10px;
  align-items: center;
}

.ts-board-select {
  flex: 1.6;
  height: 42px !important;
  font-size: 13.5px !important;
  font-weight: 600 !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  padding: 8px 12px !important;
  background-color: #ffffff !important;
  color: #0f172a !important;
}

.ts-board-filter-input {
  flex: 1;
  height: 42px !important;
  font-size: 13px !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  padding: 8px 12px !important;
  background-color: #f8fafc !important;
}

.ts-board-filter-input:focus {
  background-color: #ffffff !important;
}

.ts-search-deck-input {
  flex: 1;
  height: 42px !important;
  font-size: 13px !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  padding: 8px 12px !important;
}

.ts-filter-assigned-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 42px;
  padding: 0 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  background-color: #f1f5f9;
  border: 1.5px solid #cbd5e1;
  color: #475569;
  transition: all 0.2s ease;
}

.ts-filter-assigned-btn:hover {
  background-color: #e2e8f0;
  color: #0f172a;
}

.ts-filter-assigned-btn.active {
  background-color: #e0f2fe;
  border-color: #0284c7;
  color: #0369a1;
  box-shadow: 0 1px 3px rgba(2, 132, 199, 0.2);
}

.ts-filter-icon {
  font-size: 13px;
}

.ts-header-badges {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ts-badge-filtered {
  background-color: #e0f2fe;
  color: #0369a1;
}

/* Cabeçalho das Colunas do Grid de Cartões */
.ts-deck-table-header {
  display: flex;
  align-items: center;
  background-color: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  padding: 10px 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: #64748b;
  letter-spacing: 0.5px;
}

.ts-col-th.ts-th-task {
  flex: 2 1 360px;
}

.ts-col-th.ts-th-dates {
  flex: 1.4 1 240px;
}

.ts-col-th.ts-th-effort {
  flex: 1 1 160px;
  text-align: center;
}

.ts-col-th.ts-th-actions {
  flex: 1.1 1 180px;
  text-align: right;
}

/* Linha de Cartão */
.ts-deck-task-row {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  transition: background-color 0.15s ease;
  gap: 16px;
}

.ts-deck-task-row:hover {
  background-color: #f8fafc;
}

/* Coluna 1: Tarefa */
.ts-deck-col-task {
  flex: 2 1 360px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.ts-deck-task-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.ts-deck-task-id {
  font-size: 12px;
  font-weight: 800;
  color: #0284c7;
  background-color: #e0f2fe;
  padding: 2px 7px;
  border-radius: 6px;
}

.ts-deck-task-name {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.3;
}

.ts-deck-done-badge {
  background-color: #dcfce7;
  color: #15803d;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
}

.ts-deck-task-pills {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

/* Coluna 2: Cronograma */
.ts-deck-col-dates {
  flex: 1.4 1 240px;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.ts-date-field {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
}

.ts-date-field-label {
  font-weight: 600;
  color: #64748b;
  width: 44px;
}

.ts-date-field-val {
  font-weight: 600;
  color: #1e293b;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.ts-date-field-val.ts-text-muted {
  color: #94a3b8;
  font-weight: 500;
}

.ts-due-tag {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  margin-left: 4px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.ts-due-tag.overdue {
  background-color: #fee2e2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.ts-due-tag.soon {
  background-color: #fef3c7;
  color: #d97706;
  border: 1px solid #fde68a;
}

.ts-date-field-val.ts-due-overdue {
  color: #dc2626;
  font-weight: 700;
}

.ts-date-field-val.ts-due-soon {
  color: #d97706;
  font-weight: 700;
}

/* Coluna 3: Esforço */
.ts-deck-col-effort {
  flex: 1 1 160px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
}

.ts-effort-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 8px;
  background-color: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 13px;
  font-weight: 700;
}

.ts-effort-pill.has-hours {
  background-color: #f0fdf4;
  border-color: #bbf7d0;
  color: #16a34a;
}

.ts-effort-icon {
  font-size: 14px;
}

.ts-effort-time {
  font-size: 13.5px;
  font-weight: 800;
  letter-spacing: 0.2px;
}

.ts-effort-caption {
  font-size: 11px;
  color: #94a3b8;
  font-weight: 500;
}

/* Coluna 4: Ações */
.ts-deck-col-actions {
  flex: 1.1 1 180px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.ts-btn-deck {
  padding: 8px 14px !important;
  font-size: 12.5px !important;
  font-weight: 600 !important;
  border-radius: 7px !important;
  cursor: pointer !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 5px !important;
  transition: all 0.15s ease !important;
  border: none !important;
  white-space: nowrap !important;
}

.ts-btn-deck-hist {
  background-color: #f1f5f9 !important;
  color: #334155 !important;
  border: 1px solid #cbd5e1 !important;
}

.ts-btn-deck-hist:hover {
  background-color: #e2e8f0 !important;
  color: #0f172a !important;
}

.ts-btn-deck-log {
  background-color: #0082c9 !important;
  color: #ffffff !important;
  box-shadow: 0 1px 3px rgba(0, 130, 201, 0.3) !important;
}

.ts-btn-deck-log:hover {
  background-color: #006da8 !important;
  box-shadow: 0 2px 5px rgba(0, 130, 201, 0.4) !important;
  transform: translateY(-1px) !important;
}
</style>
