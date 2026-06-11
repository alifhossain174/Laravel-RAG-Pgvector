const STATUS_POLL_INTERVAL = 2500;
const STATUS_MAX_RETRY_DELAY = 15000;
const STATUS_FINAL_STATES = new Set(['ready', 'failed']);
const STATUS_PROCESSING_STATES = new Set(['uploaded', 'processing', 'text_extracted', 'chunked', 'embedded']);
const pollers = new Map();

let observerStarted = false;

const devLog = (...args) => {
    if (import.meta.env.DEV) {
        console.debug('[document-status-poller]', ...args);
    }
};

const wrappersForUrl = (url) => Array.from(document.querySelectorAll('[data-document-status-poller]'))
    .filter((element) => element.dataset.documentStatusUrl === url);

const textValue = (value, fallback = '-') => {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }

    return String(value);
};

const updateTextTargets = (wrapper, selector, value, fallback = '-') => {
    wrapper.querySelectorAll(selector).forEach((target) => {
        target.textContent = textValue(value, fallback);
    });
};

const updateProcessedAt = (wrapper, payload) => {
    wrapper.querySelectorAll('[data-document-processed-at]').forEach((target) => {
        const format = target.dataset.documentProcessedAtFormat || 'formatted';
        const prefix = target.dataset.documentProcessedAtPrefix || '';
        const value = format === 'relative' ? payload.processed_at_relative : payload.processed_at;

        target.textContent = `${prefix}${textValue(value)}`;
    });
};

const updateFailedReason = (wrapper, payload) => {
    wrapper.querySelectorAll('[data-document-failed-reason]').forEach((target) => {
        const textTarget = target.querySelector('[data-document-failed-reason-text]') || target;
        const reason = payload.failed_reason || '';

        textTarget.textContent = reason;
        target.classList.toggle('hidden', reason === '');
    });
};

const setActionDisabledState = (action, disabled) => {
    if ('disabled' in action) {
        action.disabled = disabled;
    }

    action.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    action.classList.toggle('pointer-events-none', disabled);
    action.classList.toggle('opacity-50', disabled);
    action.classList.toggle('cursor-not-allowed', disabled);

    if (disabled && action.matches('input[type="checkbox"], input[type="radio"]')) {
        action.checked = false;
    }
};

const updateChatActions = (wrapper, payload) => {
    wrapper.querySelectorAll('[data-document-chat-action]').forEach((action) => {
        const canChat = Boolean(payload.can_chat);

        setActionDisabledState(action, !canChat);

        if (action.hasAttribute('data-document-hide-until-ready')) {
            action.classList.toggle('hidden', !canChat);
        }

        if (action.dataset.documentReadyHref && canChat) {
            action.setAttribute('href', action.dataset.documentReadyHref);
        }

        if (canChat) {
            action.setAttribute('title', 'Document is ready for chat.');
        }
    });
};

const updateWrapper = (wrapper, payload) => {
    wrapper.dataset.documentCurrentStatus = payload.status;

    wrapper.querySelectorAll('[data-document-status-badge]').forEach((target) => {
        target.innerHTML = payload.status_badge_html || '';
    });

    updateTextTargets(wrapper, '[data-document-status-label]', payload.status_label);
    updateTextTargets(wrapper, '[data-document-total-pages]', payload.total_pages);
    updateTextTargets(wrapper, '[data-document-total-chunks]', payload.total_chunks, '0');
    updateProcessedAt(wrapper, payload);
    updateFailedReason(wrapper, payload);
    updateChatActions(wrapper, payload);

    wrapper.querySelectorAll('[data-document-processing-timeline]').forEach((target) => {
        target.innerHTML = payload.timeline_html || '';
    });
};

const applyStatusUpdate = (url, payload) => {
    wrappersForUrl(url).forEach((wrapper) => updateWrapper(wrapper, payload));
};

const clearPollTimer = (poller) => {
    if (poller.timerId) {
        clearTimeout(poller.timerId);
        poller.timerId = null;
    }
};

const stopPolling = (url) => {
    const poller = pollers.get(url);

    if (!poller) {
        return;
    }

    clearPollTimer(poller);
    pollers.delete(url);
};

const hasProcessingWrappers = (url) => wrappersForUrl(url).some((wrapper) => {
    const status = wrapper.dataset.documentCurrentStatus || '';

    return STATUS_PROCESSING_STATES.has(status) && !STATUS_FINAL_STATES.has(status);
});

const nextRetryDelay = (poller) => {
    poller.retryDelay = poller.retryDelay
        ? Math.min(poller.retryDelay * 2, STATUS_MAX_RETRY_DELAY)
        : STATUS_POLL_INTERVAL * 2;

    return poller.retryDelay;
};

const schedulePoll = (url, delay = STATUS_POLL_INTERVAL) => {
    const poller = pollers.get(url);

    if (!poller || document.hidden || poller.timerId) {
        return;
    }

    if (!hasProcessingWrappers(url)) {
        stopPolling(url);
        return;
    }

    poller.timerId = setTimeout(() => {
        poller.timerId = null;
        poll(url);
    }, delay);
};

const poll = async (url) => {
    const poller = pollers.get(url);

    if (!poller || poller.inFlight || document.hidden) {
        return;
    }

    if (!hasProcessingWrappers(url)) {
        stopPolling(url);
        return;
    }

    poller.inFlight = true;

    try {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`Status endpoint returned ${response.status}`);
        }

        const payload = await response.json();
        poller.retryDelay = 0;
        applyStatusUpdate(url, payload);

        if (STATUS_FINAL_STATES.has(payload.status)) {
            stopPolling(url);
            return;
        }

        schedulePoll(url, STATUS_POLL_INTERVAL);
    } catch (error) {
        devLog(error);

        const currentPoller = pollers.get(url);

        if (currentPoller) {
            schedulePoll(url, nextRetryDelay(currentPoller));
        }
    } finally {
        const currentPoller = pollers.get(url);

        if (currentPoller) {
            currentPoller.inFlight = false;
        }
    }
};

const shouldPoll = (wrapper) => {
    const url = wrapper.dataset.documentStatusUrl;
    const status = wrapper.dataset.documentCurrentStatus || '';

    return url && STATUS_PROCESSING_STATES.has(status) && !STATUS_FINAL_STATES.has(status);
};

export const initDocumentStatusPoller = (root = document) => {
    const wrappers = root.matches?.('[data-document-status-poller]')
        ? [root]
        : Array.from(root.querySelectorAll?.('[data-document-status-poller]') || []);

    if (wrappers.length === 0) {
        return;
    }

    wrappers.forEach((wrapper) => {
        const url = wrapper.dataset.documentStatusUrl;

        if (!shouldPoll(wrapper)) {
            return;
        }

        if (!pollers.has(url)) {
            pollers.set(url, {
                timerId: null,
                inFlight: false,
                retryDelay: 0,
            });
        }

        schedulePoll(url);
    });
};

const pausePolling = () => {
    pollers.forEach(clearPollTimer);
};

const resumePolling = () => {
    initDocumentStatusPoller();

    Array.from(pollers.keys()).forEach((url) => {
        const poller = pollers.get(url);

        if (!poller || poller.inFlight) {
            return;
        }

        clearPollTimer(poller);
        poll(url);
    });
};

const handleVisibilityChange = () => {
    if (document.hidden) {
        pausePolling();
        return;
    }

    resumePolling();
};

const observeDocumentStatusBlocks = () => {
    if (observerStarted || !document.body) {
        return;
    }

    observerStarted = true;

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                initDocumentStatusPoller(node);
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

const bootDocumentStatusPoller = () => {
    initDocumentStatusPoller();
    observeDocumentStatusBlocks();
    document.addEventListener('visibilitychange', handleVisibilityChange);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootDocumentStatusPoller, {once: true});
} else {
    bootDocumentStatusPoller();
}
