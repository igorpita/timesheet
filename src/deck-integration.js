/**
 * Integração Direta com o Nextcloud Deck — Timesheet
 * Criado por Igor Pita
 * 
 * Este script é carregado nas telas do Nextcloud e monitora a interface
 * do Nextcloud Deck para injetar opções de apontamento de horas:
 * 1. No modal de detalhes do cartão (compatível com HTML5 History mode e Vue Router).
 * 2. Na barra lateral (sidebar) do cartão.
 * 3. Nos cartões da visualização em quadro (Kanban).
 */

(function () {
    'use strict';

    let lastCardId = null;
    let isChecking = false;

    // Intercepta history.pushState e replaceState do Vue Router
    const originalPushState = history.pushState;
    history.pushState = function () {
        originalPushState.apply(this, arguments);
        triggerCheck();
    };

    const originalReplaceState = history.replaceState;
    history.replaceState = function () {
        originalReplaceState.apply(this, arguments);
        triggerCheck();
    };

    function triggerCheck() {
        setTimeout(checkCurrentCard, 50);
        setTimeout(checkCurrentCard, 200);
        setTimeout(checkCurrentCard, 500);
    }

    function initDeckWatcher() {
        // Observa mutações no DOM
        const observer = new MutationObserver(() => {
            checkCurrentCard();
            scanBoardCards();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        window.addEventListener('hashchange', triggerCheck);
        window.addEventListener('popstate', triggerCheck);
        window.addEventListener('DOMContentLoaded', triggerCheck);

        // Polling de garantia contínua (executa a cada 500ms)
        setInterval(() => {
            checkCurrentCard();
            scanBoardCards();
        }, 500);

        triggerCheck();
    }

    /**
     * Extrai o ID do cartão a partir do pathname, hash ou search
     * Ex: /apps/deck/board/2/card/6 ou /index.php/apps/deck/board/2/card/7
     */
    function extractCardId() {
        const fullUrl = (window.location.pathname || '') + (window.location.search || '') + (window.location.hash || '');
        
        // Padrão 1: /board/2/card/6 ou /boards/2/cards/6 ou /card/6
        const match = fullUrl.match(/\/cards?\/(\d+)/i);
        if (match && match[1]) {
            return match[1];
        }

        // Padrão 2: ?card=6
        const queryMatch = fullUrl.match(/[?&]card=(\d+)/i);
        if (queryMatch && queryMatch[1]) {
            return queryMatch[1];
        }

        // Padrão 3: Atributos no DOM (modal ou aside)
        const modalEl = document.querySelector('.modal-container[data-card-id], [role="dialog"][data-card-id], aside.app-sidebar[data-card-id]');
        if (modalEl) {
            return modalEl.getAttribute('data-card-id');
        }

        return null;
    }

    function extractCardTitle(cardId) {
        const modal = document.querySelector('.modal__card, .modal-container, [role="dialog"], aside.app-sidebar');
        if (modal) {
            const headings = modal.querySelectorAll('input.app-sidebar-header__mainname, .app-sidebar-header__mainname, h1, h2, h3, h4, input.card-title, textarea, [contenteditable]');
            for (const el of headings) {
                const text = (el.value || el.textContent || '').trim();
                if (text && !text.includes('Detalhes do cartão') && !text.includes('Timesheet') && text.length > 2) {
                    return text;
                }
            }
        }
        return `Cartão #${cardId}`;
    }

    /**
     * Localiza o container estritamente dentro do modal do cartão do Nextcloud Deck.
     * Retorna null se o modal ainda estiver abrindo ou renderizando, evitando vazar para a janela principal.
     */
    function findCardTargetContainer() {
        // 1. Prioridade máxima: Aba "Detalhes" ativa do cartão do Deck
        const activeTab = document.querySelector(
            '.modal__card section.app-sidebar__tab--active, ' +
            '.modal-container section.app-sidebar__tab--active, ' +
            '#details.app-sidebar__tab, ' +
            'section[aria-labelledby="details"], ' +
            'aside.app-sidebar section.app-sidebar__tab--active'
        );
        if (activeTab) {
            return activeTab;
        }

        // 2. Qualquer aba ativa ou painel de abas dentro do modal do cartão
        const modalTab = document.querySelector(
            '.modal__card .app-sidebar__tab, ' +
            '.modal-container .app-sidebar__tab, ' +
            '.modal-container .app-sidebar-tabs__content, ' +
            '.modal__card .app-sidebar-tabs__content'
        );
        if (modalTab) {
            return modalTab;
        }

        // 3. Fallback restrito: container de abas dentro do modal do cartão
        const modalBody = document.querySelector(
            '.modal__card .app-sidebar-tabs, ' +
            '.modal-container .modal-container__content, ' +
            '.modal__card .app-sidebar'
        );
        if (modalBody) {
            return modalBody;
        }

        // Se o modal do cartão ainda não está no DOM, NÃO injeta em nada da janela principal!
        return null;
    }

    async function checkCurrentCard() {
        if (isChecking) return;
        isChecking = true;

        try {
            const cardId = extractCardId();
            if (!cardId) {
                lastCardId = null;
                // Remove qualquer widget remanescente ao fechar o cartão
                document.querySelectorAll('.oab-deck-timesheet-box').forEach(el => el.remove());
                return;
            }

            // Encontra o container alvo estritamente DENTRO do modal do cartão
            const targetContainer = findCardTargetContainer();
            if (!targetContainer) {
                // Modal do cartão ainda está abrindo ou animando no Vue; aguarda próximo ciclo
                return;
            }

            // Remove qualquer widget que tenha sido inserido fora do container correto (limpeza de resquícios)
            const misplaced = document.querySelectorAll('.oab-deck-timesheet-box');
            misplaced.forEach(el => {
                if (!targetContainer.contains(el)) {
                    el.remove();
                }
            });

            // Se o widget já existe e está corretamente posicionado dentro do container, não recria
            const existingWidget = document.getElementById(`deck-timesheet-widget-${cardId}`);
            if (existingWidget && targetContainer.contains(existingWidget)) {
                return;
            }

            lastCardId = cardId;
            await injectTimesheetWidget(cardId, targetContainer);
        } finally {
            isChecking = false;
        }
    }

    async function injectTimesheetWidget(cardId, targetContainer) {
        if (!targetContainer) {
            return;
        }

        // Evita duplicidade se já estiver injetado dentro do alvo
        if (targetContainer.querySelector(`#deck-timesheet-widget-${cardId}`)) {
            return;
        }

        // Consulta horas já acumuladas no cartão via API
        let totalHours = '00h00';
        try {
            const url = window.OC.generateUrl(`/apps/oab_timesheet/api/v1/deck/cards/${cardId}/history`);
            const res = await fetch(url, {
                headers: {
                    'requesttoken': window.OC.requestToken,
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.totalFormatted) {
                totalHours = data.totalFormatted;
            }
        } catch (e) {
            // Falha silenciosa
        }

        // Cria o widget com design moderno e adaptável
        const widget = document.createElement('div');
        widget.id = `deck-timesheet-widget-${cardId}`;
        widget.className = 'oab-deck-timesheet-box';
        widget.style.cssText = `
            background: rgba(22, 163, 74, 0.10) !important;
            border: 1px solid rgba(34, 197, 94, 0.40) !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            margin: 10px 0 14px 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            box-sizing: border-box !important;
            width: 100% !important;
        `;

        widget.innerHTML = `
            <div style="display: flex; flex-direction: column;">
                <span style="font-size: 11px; text-transform: uppercase; color: #16a34a; font-weight: 800; letter-spacing: 0.6px;">⏱️ Timesheet</span>
                <span style="font-size: 13px; color: inherit; margin-top: 2px;">
                    <strong id="deck-ts-total-${cardId}" style="font-size: 15px; font-weight: 800; color: #16a34a;">${totalHours}</strong> acumuladas neste cartão
                </span>
            </div>
            <button type="button" id="deck-open-ts-btn-${cardId}" style="background: #16a34a !important; color: #ffffff !important; border: none !important; border-radius: 6px !important; padding: 7px 14px !important; font-size: 12.5px !important; font-weight: 700 !important; cursor: pointer !important; display: flex !important; align-items: center !important; gap: 5px !important; box-shadow: 0 2px 4px rgba(0,0,0,0.15) !important; transition: all 0.15s ease !important;">
                ➕ Apontar Horas
            </button>
        `;

        // Ponto de inserção: insere no topo do container (aba Detalhes)
        if (targetContainer.firstChild) {
            targetContainer.insertBefore(widget, targetContainer.firstChild);
        } else {
            targetContainer.appendChild(widget);
        }

        // Evento de clique
        const btn = document.getElementById(`deck-open-ts-btn-${cardId}`);
        if (btn) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const title = extractCardTitle(cardId);
                openInlineTimesheetModal(cardId, title);
            });
        }
    }

    /**
     * Injeta atalhos de apontamento diretamente nos cartões do Kanban na visualização geral
     */
    function scanBoardCards() {
        const cards = document.querySelectorAll('.card, .deck-card, [data-card-id]');
        cards.forEach((cardEl) => {
            const cardId = cardEl.getAttribute('data-card-id') || (cardEl.id ? cardEl.id.replace(/\D/g, '') : null);
            if (!cardId || cardEl.querySelector('.oab-deck-quick-action')) {
                return;
            }

            const titleEl = cardEl.querySelector('.card-title, h3, .card-upper, .card-menu');
            if (!titleEl) return;

            const quickBtn = document.createElement('button');
            quickBtn.type = 'button';
            quickBtn.className = 'oab-deck-quick-action';
            quickBtn.title = 'Apontar horas neste cartão (Timesheet)';
            quickBtn.style.cssText = `
                background: rgba(22, 163, 74, 0.15) !important;
                border: 1px solid rgba(34, 197, 94, 0.4) !important;
                color: #22c55e !important;
                border-radius: 4px !important;
                padding: 3px 8px !important;
                font-size: 11px !important;
                font-weight: 700 !important;
                cursor: pointer !important;
                margin-top: 6px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
                transition: background 0.15s ease !important;
            `;
            quickBtn.innerHTML = '⏱️ Apontar';

            quickBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const cardTitle = cardEl.querySelector('.card-title, h3')?.textContent?.trim() || `Cartão #${cardId}`;
                openInlineTimesheetModal(cardId, cardTitle);
            });

            cardEl.appendChild(quickBtn);
        });
    }

    function openInlineTimesheetModal(cardId, cardTitle) {
        const existing = document.getElementById('deck-ts-inline-modal');
        if (existing) existing.remove();

        const todayStr = new Date().toISOString().substring(0, 10);

        const modal = document.createElement('div');
        modal.id = 'deck-ts-inline-modal';
        modal.style.cssText = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(15, 23, 42, 0.75) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 2147483647 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            backdrop-filter: blur(4px) !important;
            pointer-events: auto !important;
        `;

        // Interrompe captura e propagação de foco/teclado para anular o focus-trap do modal do Deck
        ['focusin', 'focusout', 'focus', 'blur', 'keydown', 'keyup', 'keypress', 'mousedown', 'mouseup', 'click', 'pointerdown', 'pointerup', 'touchstart', 'touchend'].forEach(evt => {
            modal.addEventListener(evt, (e) => {
                e.stopPropagation();
            }, true);
        });

        modal.innerHTML = `
            <div style="background: #1e293b !important; color: #f8fafc !important; border: 1px solid #334155 !important; border-radius: 12px !important; width: 500px !important; max-width: 94vw !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; overflow: hidden !important; animation: fadeIn 0.15s ease !important; pointer-events: auto !important; user-select: text !important;">
                <div style="padding: 16px 22px !important; border-bottom: 1px solid #334155 !important; display: flex !important; justify-content: space-between !important; align-items: center !important; background: #0f172a !important;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 20px;">⏱️</span>
                        <h3 style="margin: 0 !important; font-size: 16px !important; font-weight: 700 !important; color: #ffffff !important;">Apontar Horas — Deck</h3>
                    </div>
                    <button type="button" id="deck-ts-close-modal" style="background: transparent !important; border: none !important; font-size: 24px !important; cursor: pointer !important; color: #94a3b8 !important; line-height: 1 !important; padding: 4px 8px !important;">&times;</button>
                </div>
                
                <form id="deck-ts-form" style="padding: 22px !important; pointer-events: auto !important;">
                    <div style="margin-bottom: 16px;">
                        <label style="display: block !important; font-size: 12px !important; font-weight: 700 !important; margin-bottom: 6px !important; color: #94a3b8 !important; text-transform: uppercase !important; letter-spacing: 0.5px !important;">Cartão do Deck</label>
                        <input type="text" value="#${cardId} — ${cardTitle}" readonly tabindex="-1" style="width: 100% !important; padding: 10px 12px !important; border: 1px solid #334155 !important; border-radius: 6px !important; background: #0f172a !important; font-size: 13px !important; font-weight: 600 !important; color: #94a3b8 !important; box-sizing: border-box !important;" />
                    </div>

                    <div style="display: flex; gap: 14px; margin-bottom: 16px;">
                        <div style="flex: 1;">
                            <label style="display: block !important; font-size: 12px !important; font-weight: 700 !important; margin-bottom: 6px !important; color: #94a3b8 !important; text-transform: uppercase !important; letter-spacing: 0.5px !important;">Data da Atividade *</label>
                            <input type="date" id="deck-ts-work-date" value="${todayStr}" max="${todayStr}" required style="width: 100% !important; padding: 9px 12px !important; border: 1px solid #475569 !important; border-radius: 6px !important; background: #0f172a !important; color: #ffffff !important; font-size: 13px !important; font-weight: 600 !important; box-sizing: border-box !important; color-scheme: dark !important; pointer-events: auto !important; cursor: pointer !important;" />
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block !important; font-size: 12px !important; font-weight: 700 !important; margin-bottom: 6px !important; color: #94a3b8 !important; text-transform: uppercase !important; letter-spacing: 0.5px !important;">Duração *</label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" id="deck-ts-hours" value="1" min="0" max="24" style="width: 60px !important; text-align: center !important; padding: 9px !important; border: 1px solid #475569 !important; border-radius: 6px !important; background: #0f172a !important; color: #ffffff !important; font-size: 14px !important; font-weight: 700 !important; color-scheme: dark !important; pointer-events: auto !important;" />
                                <span style="font-size: 13px !important; font-weight: 600 !important; color: #94a3b8 !important;">h</span>
                                <input type="number" id="deck-ts-mins" value="0" min="0" max="59" step="5" style="width: 60px !important; text-align: center !important; padding: 9px !important; border: 1px solid #475569 !important; border-radius: 6px !important; background: #0f172a !important; color: #ffffff !important; font-size: 14px !important; font-weight: 700 !important; color-scheme: dark !important; pointer-events: auto !important;" />
                                <span style="font-size: 13px !important; font-weight: 600 !important; color: #94a3b8 !important;">min</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="display: block !important; font-size: 12px !important; font-weight: 700 !important; margin-bottom: 6px !important; color: #94a3b8 !important; text-transform: uppercase !important; letter-spacing: 0.5px !important;">Descrição da Atividade *</label>
                        <textarea id="deck-ts-description" rows="3" placeholder="Descreva as atividades executadas neste cartão..." required style="width: 100% !important; padding: 10px 12px !important; border: 1px solid #475569 !important; border-radius: 6px !important; background: #0f172a !important; color: #ffffff !important; font-size: 13px !important; box-sizing: border-box !important; resize: vertical !important; pointer-events: auto !important; user-select: text !important;"></textarea>
                    </div>

                    <div id="deck-ts-error" style="display: none; padding: 10px 14px !important; background: #7f1d1d !important; color: #fecaca !important; border: 1px solid #b91c1c !important; border-radius: 6px !important; font-size: 13px !important; margin-bottom: 16px !important; font-weight: 600 !important;"></div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" id="deck-ts-btn-cancel" style="padding: 10px 18px !important; border: 1px solid #475569 !important; background: #334155 !important; border-radius: 6px !important; font-size: 13px !important; font-weight: 600 !important; color: #e2e8f0 !important; cursor: pointer !important;">Cancelar</button>
                        <button type="submit" id="deck-ts-btn-submit" style="padding: 10px 22px !important; border: none !important; background: #16a34a !important; border-radius: 6px !important; font-size: 13px !important; font-weight: 700 !important; color: #ffffff !important; cursor: pointer !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;">Salvar Apontamento</button>
                    </div>
                </form>
            </div>
        `;

        // Se houver um modal do Deck aberto, anexa DENTRO dele para que o focus-trap do Nextcloud o considere interno!
        const deckModal = document.querySelector('.modal-container, [role="dialog"], .modal-wrapper') || document.body;
        deckModal.appendChild(modal);

        const closeModal = () => modal.remove();
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
        document.getElementById('deck-ts-close-modal').addEventListener('click', closeModal);
        document.getElementById('deck-ts-btn-cancel').addEventListener('click', closeModal);

        // Foco inicial automático no campo de descrição
        setTimeout(() => {
            const desc = document.getElementById('deck-ts-description');
            if (desc) {
                desc.focus();
            }
        }, 100);

        // Submissão do formulário
        document.getElementById('deck-ts-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const errBox = document.getElementById('deck-ts-error');
            errBox.style.display = 'none';

            const workDate = document.getElementById('deck-ts-work-date').value;
            const hours = parseInt(document.getElementById('deck-ts-hours').value || 0, 10);
            const mins = parseInt(document.getElementById('deck-ts-mins').value || 0, 10);
            const description = (document.getElementById('deck-ts-description').value || '').trim();

            if (!workDate) {
                errBox.textContent = 'Por favor, informe a data da atividade.';
                errBox.style.display = 'block';
                return;
            }

            if (workDate > todayStr) {
                errBox.textContent = 'Não é permitido registrar apontamentos com data futura.';
                errBox.style.display = 'block';
                return;
            }

            const durationMinutes = (hours * 60) + mins;
            if (durationMinutes <= 0) {
                errBox.textContent = 'A duração deve ser de pelo menos 1 minuto.';
                errBox.style.display = 'block';
                return;
            }

            if (!description) {
                errBox.textContent = 'Por favor, descreva a atividade realizada.';
                errBox.style.display = 'block';
                return;
            }

            const submitBtn = document.getElementById('deck-ts-btn-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Gravando...';

            try {
                const url = window.OC.generateUrl('/apps/oab_timesheet/api/v1/entries');
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'requesttoken': window.OC.requestToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        sourceType: 'DECK',
                        externalId: cardId,
                        externalTitle: cardTitle,
                        workDate,
                        durationMinutes,
                        description
                    })
                });

                const data = await res.json();
                if (data.success) {
                    closeModal();
                    // Atualiza acumulador visual no cartão
                    refreshCardTotal(cardId);
                    showToastSuccess('Apontamento de horas salvo com sucesso no Timesheet!');
                } else {
                    errBox.textContent = data.error || 'Erro ao salvar apontamento.';
                    errBox.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Salvar Apontamento';
                }
            } catch (err) {
                errBox.textContent = 'Erro de comunicação: ' + err.message;
                errBox.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Salvar Apontamento';
            }
        });
    }

    async function refreshCardTotal(cardId) {
        try {
            const url = window.OC.generateUrl(`/apps/oab_timesheet/api/v1/deck/cards/${cardId}/history`);
            const res = await fetch(url, {
                headers: {
                    'requesttoken': window.OC.requestToken,
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            const label = document.getElementById(`deck-ts-total-${cardId}`);
            if (label && data.totalFormatted) {
                label.textContent = data.totalFormatted;
            }
        } catch (e) {
            console.error(e);
        }
    }

    function showToastSuccess(msg) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed !important;
            bottom: 28px !important;
            right: 28px !important;
            background: #15803d !important;
            color: #ffffff !important;
            padding: 14px 22px !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5) !important;
            z-index: 2147483647 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        `;
        toast.innerHTML = `<span style="font-size: 16px;">✓</span> <span>${msg}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDeckWatcher);
    } else {
        initDeckWatcher();
    }
})();
