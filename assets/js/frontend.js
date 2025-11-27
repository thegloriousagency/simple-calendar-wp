import '../css/frontend.css';

const settings = window.CECCalendarSettings || {};

const buildRequestUrl = (params = {}) => {
    const url = new URL(settings.restUrl);
    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            return;
        }
        url.searchParams.append(key, value);
    });

    return url.toString();
};

const fetchMonthView = async (params) => {
    const response = await fetch(buildRequestUrl(params), {
        headers: {
            'X-WP-Nonce': settings.nonce || '',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Unable to load calendar data');
    }

    return response.json();
};

const replaceCalendar = (current, html) => {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const nextCalendar = temp.querySelector('[data-cec-calendar]');

    if (!nextCalendar) {
        throw new Error('Calendar HTML missing data attribute.');
    }

    current.replaceWith(nextCalendar);
    initCalendar(nextCalendar);
};

const updateCalendar = async (container, params) => {
    container.classList.add('is-loading');

    try {
        if (window.console && typeof window.console.debug === 'function') {
            window.console.debug('[CEC] Updating calendar', params);
        }
        const data = await fetchMonthView(params);
        replaceCalendar(container, data.html);
    } catch (error) {
        console.error('Church Events Calendar:', error);
        container.classList.remove('is-loading');
    }
};

const handleNavigation = (event, container) => {
    const target = event.target.closest('[data-cec-nav]');
    if (!target) {
        return;
    }

    event.preventDefault();

    const action = target.dataset.cecNav;
    let year = parseInt(container.dataset.cecYear, 10);
    let month = parseInt(container.dataset.cecMonth, 10);
    const category = container.dataset.cecCategory || '';
    const language = container.dataset.cecLanguage || '';
    if (window.console && typeof window.console.debug === 'function') {
        window.console.debug('[CEC] Navigation request', {
            action,
            year,
            month,
            category,
            language,
            targetYear: target.dataset.targetYear,
            targetMonth: target.dataset.targetMonth,
        });
    }

    if (action === 'today' && settings.today) {
        year = parseInt(settings.today.year, 10);
        month = parseInt(settings.today.month, 10);
    } else {
        year = parseInt(target.dataset.targetYear || year, 10);
        month = parseInt(target.dataset.targetMonth || month, 10);
    }

    updateCalendar(container, {
        year,
        month,
        category,
        lang: language || undefined,
    });
};

const handleFilterChange = (event, container) => {
    const select = event.target.closest('[data-cec-filter]');
    if (!select) {
        return;
    }

    const category = select.value || '';
    const language = container.dataset.cecLanguage || '';
    if (window.console && typeof window.console.debug === 'function') {
        window.console.debug('[CEC] Filter change', {
            category,
            language,
        });
    }
    updateCalendar(container, {
        year: parseInt(container.dataset.cecYear, 10),
        month: parseInt(container.dataset.cecMonth, 10),
        category,
        lang: language || undefined,
    });
};

function initCalendar(container) {
    if (!container || container.dataset.cecInitialized) {
        return;
    }

    container.dataset.cecInitialized = 'true';

    container.addEventListener('click', (event) => handleNavigation(event, container));
    container.addEventListener('change', (event) => handleFilterChange(event, container));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cec-calendar]').forEach((calendar) => initCalendar(calendar));
});
