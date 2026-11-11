<template>
  <div v-if="isOpen" class="ts-modal-overlay" @click.self="close">
    <div class="ts-modal-card">
      <div class="ts-modal-header">
        <h3 class="ts-modal-title">{{ isEditing ? 'Editar Apontamento' : 'Novo Apontamento de Horas' }}</h3>
        <button class="ts-btn-close" @click="close" title="Fechar">&times;</button>
      </div>

      <form @submit.prevent="handleSubmit" class="ts-modal-body">
        <!-- Seleção Dinâmica de Projeto e Cartão do Deck -->
        <div class="ts-deck-selector-box">
          <!-- Seleção do Quadro / Projeto -->
          <div class="ts-form-group">
            <label class="ts-field-label">Projeto / Quadro do Deck *</label>
            <div v-if="loadingBoards" class="ts-loading-text">Carregando seus projetos do Deck...</div>
            <select
              v-else
              v-model="selectedBoardId"
              @change="onBoardChange"
              class="ts-select"
              required
            >
              <option value="" disabled>-- Selecione um Projeto / Quadro --</option>
              <option v-for="board in boards" :key="board.id" :value="board.id">
                📁 {{ board.title }} {{ board.isOwner ? '(Proprietário)' : '' }}
              </option>
            </select>
          </div>

          <!-- Seleção do Cartão -->
          <div class="ts-form-group" v-if="selectedBoardId">
            <div class="ts-label-row">
              <label class="ts-field-label">Cartão / Tarefa do Projeto *</label>
              <span v-if="cards.length > 0" class="ts-badge-count">{{ cards.length }} tarefas disponíveis</span>
            </div>

            <div v-if="loadingCards" class="ts-loading-text">Carregando cartões do quadro...</div>
            <div v-else-if="cards.length === 0" class="ts-alert-warning-box">
              Nenhum cartão ativo encontrado neste quadro. Crie um cartão no Deck primeiro.
            </div>
            <select
              v-else
              v-model="selectedCardId"
              @change="onCardChange"
              class="ts-select"
              required
            >
              <option value="" disabled>-- Selecione o Cartão da Tarefa --</option>
              <option v-for="card in cards" :key="card.id" :value="card.id">
                [{{ card.stack }}] #{{ card.id }} - {{ card.title }}
              </option>
            </select>
          </div>

          <!-- Cartão Selecionado Resumo -->
          <div v-if="selectedCard" class="ts-selected-card-info">
            <div class="ts-card-info-title">🎯 <strong>{{ selectedCard.title }}</strong></div>
            <div class="ts-card-info-meta">
              <span>Coluna: <strong>{{ selectedCard.stack }}</strong></span>
              <span v-if="selectedCard.dueDate">Prazo: <strong>{{ formatDate(selectedCard.dueDate) }}</strong></span>
            </div>
          </div>
        </div>

        <div class="ts-form-row">
          <!-- Data -->
          <div class="ts-form-group ts-col">
            <label class="ts-field-label">Data da Atividade *</label>
            <input type="date" v-model="form.workDate" :max="todayDate" required class="ts-input" />
          </div>

          <!-- Duração (Horas e Minutos) -->
          <div class="ts-form-group ts-col">
            <label class="ts-field-label">Duração *</label>
            <div class="ts-duration-inputs">
              <input
                type="number"
                min="0"
                max="24"
                v-model.number="hours"
                placeholder="0"
                class="ts-input ts-input-sm"
              />
              <span class="ts-unit">h</span>
              <input
                type="number"
                min="0"
                max="59"
                step="5"
                v-model.number="minutes"
                placeholder="00"
                class="ts-input ts-input-sm"
              />
              <span class="ts-unit">min</span>
            </div>
            <small class="ts-hint">Total: {{ totalMinutesCalculated }} min ({{ formatHours(totalMinutesCalculated) }})</small>
          </div>
        </div>

        <!-- Descrição do trabalho -->
        <div class="ts-form-group">
          <label class="ts-field-label">Descrição do Trabalho Realizado *</label>
          <textarea
            v-model="form.description"
            rows="3"
            placeholder="Descreva detalhadamente as tarefas executadas no período..."
            required
            class="ts-textarea"
          ></textarea>
        </div>

        <div v-if="errorMessage" class="ts-alert ts-alert-danger">
          ⚠️ {{ errorMessage }}
        </div>

        <div class="ts-modal-footer">
          <button type="button" class="ts-btn ts-btn-secondary" @click="close">Cancelar</button>
          <button type="submit" class="ts-btn ts-btn-primary" :disabled="loading || !form.externalId">
            {{ loading ? 'Salvando...' : (isEditing ? 'Atualizar Registro' : 'Salvar Apontamento') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { TimeEntryService } from '../services/api';

export default {
  name: 'NewEntryModal',
  props: {
    isOpen: { type: Boolean, default: false },
    initialData: { type: Object, default: null }
  },
  emits: ['close', 'saved'],
  data() {
    return {
      hours: 1,
      minutes: 0,
      loading: false,
      loadingBoards: false,
      loadingCards: false,
      errorMessage: '',
      boards: [],
      cards: [],
      selectedBoardId: '',
      selectedCardId: '',
      form: {
        id: null,
        sourceType: 'DECK',
        externalId: '',
        externalTaskId: '',
        externalTitle: '',
        workDate: new Date().toISOString().substring(0, 10),
        durationMinutes: 60,
        description: ''
      }
    };
  },
  computed: {
    isEditing() {
      return !!this.initialData?.id;
    },
    totalMinutesCalculated() {
      return (parseInt(this.hours || 0, 10) * 60) + parseInt(this.minutes || 0, 10);
    },
    selectedCard() {
      return this.cards.find(c => String(c.id) === String(this.selectedCardId));
    },
    todayDate() {
      return new Date().toISOString().substring(0, 10);
    }
  },
  watch: {
    async isOpen(val) {
      if (val) {
        this.errorMessage = '';
        await this.loadBoards();
        if (this.initialData) {
          this.form = { ...this.initialData };
          const totalMins = this.initialData.durationMinutes || 60;
          this.hours = Math.floor(totalMins / 60);
          this.minutes = totalMins % 60;
          if (this.initialData.externalId) {
            this.selectedCardId = String(this.initialData.externalId);
          }
        } else {
          this.resetForm();
        }
      }
    }
  },
  methods: {
    formatHours(minutes) {
      const h = Math.floor(minutes / 60);
      const m = minutes % 60;
      return `${String(h).padStart(2, '0')}h${String(m).padStart(2, '0')}`;
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      return new Date(dateStr).toLocaleDateString('pt-BR');
    },
    resetForm() {
      this.hours = 1;
      this.minutes = 0;
      this.selectedBoardId = this.boards.length > 0 ? this.boards[0].id : '';
      this.selectedCardId = '';
      this.form = {
        id: null,
        sourceType: 'DECK',
        externalId: '',
        externalTaskId: '',
        externalTitle: '',
        workDate: new Date().toISOString().substring(0, 10),
        durationMinutes: 60,
        description: ''
      };
      if (this.selectedBoardId) {
        this.loadCards(this.selectedBoardId);
      }
    },
    async loadBoards() {
      this.loadingBoards = true;
      try {
        const res = await TimeEntryService.getDeckBoards();
        if (res?.success && Array.isArray(res.data)) {
          this.boards = res.data;
          if (!this.selectedBoardId && this.boards.length > 0) {
            this.selectedBoardId = this.boards[0].id;
            await this.loadCards(this.selectedBoardId);
          }
        }
      } catch (err) {
        console.error('Erro ao carregar quadros do Deck:', err);
      } finally {
        this.loadingBoards = false;
      }
    },
    async loadCards(boardId) {
      if (!boardId) {
        this.cards = [];
        return;
      }
      this.loadingCards = true;
      try {
        const res = await TimeEntryService.getDeckCards(boardId);
        if (res?.success && Array.isArray(res.data)) {
          this.cards = res.data;
          if (this.cards.length > 0 && !this.selectedCardId) {
            this.selectedCardId = this.cards[0].id;
            this.onCardChange();
          }
        } else {
          this.cards = [];
        }
      } catch (err) {
        console.error('Erro ao carregar cartões do Deck:', err);
        this.cards = [];
      } finally {
        this.loadingCards = false;
      }
    },
    onBoardChange() {
      this.selectedCardId = '';
      this.form.externalId = '';
      this.form.externalTitle = '';
      this.loadCards(this.selectedBoardId);
    },
    onCardChange() {
      const card = this.selectedCard;
      if (card) {
        this.form.externalId = String(card.id);
        const board = this.boards.find(b => String(b.id) === String(this.selectedBoardId));
        const boardTitle = board ? board.title : '';
        this.form.externalTitle = `[${boardTitle}] ${card.title}`;
      }
    },
    close() {
      this.$emit('close');
    },
    async handleSubmit() {
      this.errorMessage = '';
      if (!this.form.workDate) {
        this.errorMessage = 'A data da atividade é obrigatória.';
        return;
      }
      if (this.form.workDate > this.todayDate) {
        this.errorMessage = 'Não é permitido registrar apontamentos com data futura.';
        return;
      }
      const totalMinutes = this.totalMinutesCalculated;
      if (totalMinutes <= 0) {
        this.errorMessage = 'A duração deve ser de pelo menos 1 minuto.';
        return;
      }

      if (!this.form.externalId) {
        this.errorMessage = 'Por favor, selecione um cartão do projeto Deck.';
        return;
      }

      this.form.durationMinutes = totalMinutes;

      this.loading = true;
      try {
        this.$emit('saved', { ...this.form });
        this.close();
      } catch (err) {
        this.errorMessage = err.message || 'Erro ao salvar apontamento.';
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>
