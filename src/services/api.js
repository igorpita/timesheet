import axios from 'axios';

// Resolve Nextcloud CSRF token e Base URL
const getRequestToken = () => {
    return window.OC?.requestToken || '';
};

const getBaseUrl = () => {
    if (window.OC?.generateUrl) {
        return window.OC.generateUrl('/apps/oab_timesheet/api/v1');
    }
    return '/apps/oab_timesheet/api/v1';
};

export const apiClient = axios.create({
    headers: {
        'requesttoken': getRequestToken(),
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    }
});

// Interceptor para manter o token atualizado
apiClient.interceptors.request.use((config) => {
    config.baseURL = getBaseUrl();
    const token = getRequestToken();
    if (token) {
        config.headers['requesttoken'] = token;
    }
    return config;
});

export const TimeEntryService = {
    async getMyEntries(startDate, endDate) {
        const response = await apiClient.get('/entries', {
            params: { startDate, endDate }
        });
        return response.data;
    },

    async createEntry(entryData) {
        const response = await apiClient.post('/entries', entryData);
        return response.data;
    },

    async updateEntry(id, entryData) {
        const response = await apiClient.put(`/entries/${id}`, entryData);
        return response.data;
    },

    async deleteEntry(id) {
        const response = await apiClient.delete(`/entries/${id}`);
        return response.data;
    },

    // Deck
    async getDeckBoards() {
        const response = await apiClient.get('/deck/boards');
        return response.data;
    },

    async getDeckCards(boardId) {
        const response = await apiClient.get(`/deck/boards/${boardId}/cards`);
        return response.data;
    },

    async searchDeckCards(query) {
        const response = await apiClient.get('/deck/search', { params: { q: query } });
        return response.data;
    },

    async getDeckCardHistory(cardId) {
        const response = await apiClient.get(`/deck/cards/${cardId}/history`);
        return response.data;
    },

    // GLPI
    async searchGlpiTickets(query) {
        const response = await apiClient.get('/glpi/tickets', { params: { q: query } });
        return response.data;
    },

    async getGlpiSyncStatus() {
        const response = await apiClient.get('/glpi/sync-status');
        return response.data;
    },

    async getGlpiConfig() {
        const response = await apiClient.get('/glpi/config');
        return response.data;
    },

    async saveGlpiConfig(configData) {
        const response = await apiClient.post('/glpi/config', configData);
        return response.data;
    },

    async testGlpiConnection(configData) {
        const response = await apiClient.post('/glpi/test-connection', configData);
        return response.data;
    },

    // Equipe (Gestor)
    async getTeamEntries(startDate, endDate, sourceType) {
        const response = await apiClient.get('/team/entries', {
            params: { startDate, endDate, sourceType }
        });
        return response.data;
    },

    async getTeamSummary(startDate, endDate) {
        const response = await apiClient.get('/team/summary', {
            params: { startDate, endDate }
        });
        return response.data;
    },

    async getReports(startDate, endDate, userUid) {
        const response = await apiClient.get('/team/reports', {
            params: { startDate, endDate, userUid }
        });
        return response.data;
    }
};

