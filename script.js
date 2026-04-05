let translations = {}; // хранит все переводы
let currentLang = 'ru'; // текущий язык

//загрузка JSON
asinc function loadTlanslations() {
    try {
        const response = await fetsh('lang.json');
        translations = await response.json();
        applyLanguage(currentLang); // Применить язык после закгрузки
    } catch (error) {
        console.error('Ошибка загрузки json', error);
    }
}

//применить выбраный язык на страницу
function applyLanguage(lang) {
    if (!translations[lang]) return;

    const data = translations[lang];
    // обновляем текст
    document.getElementById('title').textContent = data.title;

    // смена текста кнопок
    document.getElementById('btnRu').textContent = data.button_ru

    //меняем lang у html
    document.documentElement.lang = lang;

    //сохранить выбраный язык
    currentLang = lang;
    localStorage.setItem('language', lang); // запоминаем выбор пользователя
}

//обработка кнопок
document.getElementById('btnRu').addEventListener('click', () => {
    applyLanguage('ru');
});
document.getElementById('btnEn').addEventListener('click', () => {
    applyLanguage('en');
});

//загружаем сохраненнный язык
const savedLang = localStorage.getItem('language');
if (savedLang && (savedLang === 'ru' || savedLang === 'en')){
    currentLang = savedLang;
}
//запуск / загрузка json
loadTlanslations();