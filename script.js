let translations = {}; // Хранит все переводы
let currentLang = 'ru'; // Текущий язык

// Загрузка JSON файла
async function loadTranslations() {
    try {
        const response = await fetch('lang.json');
        translations = await response.json();
        applyLanguage(currentLang); // Применить язык после загрузки
    } catch (error) {
        console.error('Ошибка загрузки JSON:', error);
    }
}

// Применить выбранный язык на страницу
function applyLanguage(lang) {
    if (!translations[lang]) return;
    
    const data = translations[lang];
    
    // Обновляем текст на странице
    // Цикл по всем ключам в data
    for (const key in data) {
        const element = document.getElementById(key);
        if (element) {
            element.textContent = data[key];
        }
    }
    document.querySelectorAll('[data-placeholder-id]').forEach(element => {
        const key = element.getAttribute('data-placeholder-id');
        if (data[key]) {
            element.placeholder = data[key];
        }
    });

    // Меняем атрибут lang у html
    document.documentElement.lang = lang;
    
    // Сохраняем выбранный язык
    currentLang = lang;
    localStorage.setItem('language', lang); // Запоминаем выбор пользователя
}

// Обработчики кнопок
document.getElementById('btnRu').addEventListener('click', () => {
    applyLanguage('ru');
});

document.getElementById('btnEn').addEventListener('click', () => {
    applyLanguage('en');
});

// Загружаем сохранённый язык (если есть)
const savedLang = localStorage.getItem('language');
if (savedLang && (savedLang === 'ru' || savedLang === 'en')) {
    currentLang = savedLang;
}

// Запуск: загружаем JSON
loadTranslations();
