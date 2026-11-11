<template>
  <div v-if="isOpen" class="ts-modal-overlay" @click.self="close">
    <div class="ts-modal-card">
      <div class="ts-modal-header">
        <h3>Trilha de Auditoria — Apontamento #{{ entryId?.substring(0, 8) }}</h3>
        <button class="ts-btn-close" @click="close">&times;</button>
      </div>

      <div class="ts-modal-body">
        <div v-if="loading" class="ts-empty-state">
          <div class="ts-spinner"></div>
          <p>Carregando histórico de auditoria...</p>
        </div>

        <div v-else-if="logs.length === 0" class="ts-empty-state">
          <p>Nenhuma alteração registrada além da criação.</p>
        </div>

        <div v-else class="ts-timeline">
          <div v-for="log in logs" :key="log.id" class="ts-timeline-item">
            <div class="ts-timeline-badge" :class="'action-' + log.action.toLowerCase()">
              {{ log.action }}
            </div>
            <div class="ts-timeline-content">
              <div class="ts-timeline-meta">
                <strong>{{ log.userUid }}</strong> em {{ log.createdAt }}
              </div>
              <div v-if="log.changes" class="ts-changes-box">
                <div v-if="log.changes.before">
                  <span class="ts-diff-label">Antes:</span>
                  <code>{{ log.changes.before.durationFormatted }} — {{ log.changes.before.description }}</code>
                </div>
                <div v-if="log.changes.after">
                  <span class="ts-diff-label">Depois:</span>
                  <code>{{ log.changes.after.durationFormatted }} — {{ log.changes.after.description }}</code>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="ts-modal-footer">
          <button type="button" class="ts-btn ts-btn-secondary" @click="close">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { apiClient } from '../services/api';

export default {
  name: 'AuditModal',
  props: {
    isOpen: { type: Boolean, default: false },
    entryId: { type: String, default: null }
  },
  emits: ['close'],
  data() {
    return {
      logs: [],
      loading: false
    };
  },
  watch: {
    isOpen(val) {
      if (val && this.entryId) {
        this.fetchLogs();
      }
    }
  },
  methods: {
    close() {
      this.$emit('close');
    },
    async fetchLogs() {
      this.loading = true;
      try {
        const res = await apiClient.get(`/entries/${this.entryId}/audit`);
        this.logs = res.data.data || [];
      } catch (err) {
        console.error('Erro ao buscar auditoria', err);
        this.logs = [];
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

