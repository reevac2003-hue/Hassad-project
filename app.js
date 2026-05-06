/**
 * تطبيق حصاد - ملف JavaScript للتفاعلية
 * =====================================
 * يحتوي على جميع الدوال التفاعلية للواجهة
 * يشمل: القوائم، التحقق من النماذج، التأثيرات البصرية
 */

// انتظار تحميل الصفحة بالكامل
document.addEventListener('DOMContentLoaded', () => {
  // إضافة سلوك التمرير السلس
  document.documentElement.style.scrollBehavior = 'smooth';
  
  // إضافة كلاس التحميل
  document.body.classList.add('loaded');
  
  // تهيئة جميع العناصر التفاعلية
  initializeInputGuards();
  initializeMenuToggle();
  initializeScrollButtons();
  initializeFormValidation();
  initializePasswordToggle();
  initializeCardHoverEffects();
  initializeEnglishDigitsNormalization();
});


/**
 * دالة تحويل الأرقام إلى الإنجليزية على مستوى الصفحة
 * --------------------------------
 * تحوّل الأرقام العربية/الفارسية داخل النصوص إلى 0-9
 * لضمان توحيد العرض الرقمي في كامل المشروع.
 */
function initializeEnglishDigitsNormalization() {
  const map = {
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9'
  };
  const digitRegex = /[٠-٩۰-۹]/g;

  const normalizeText = (text) => text.replace(digitRegex, (ch) => map[ch] || ch);

  const walkAndNormalize = (root) => {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    const textNodes = [];
    while (walker.nextNode()) {
      textNodes.push(walker.currentNode);
    }
    textNodes.forEach((node) => {
      if (!node.nodeValue || !digitRegex.test(node.nodeValue)) {
        return;
      }
      node.nodeValue = normalizeText(node.nodeValue);
    });
  };

  walkAndNormalize(document.body);

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((added) => {
        if (added.nodeType === Node.TEXT_NODE) {
          if (added.nodeValue && digitRegex.test(added.nodeValue)) {
            added.nodeValue = normalizeText(added.nodeValue);
          }
          return;
        }
        if (added.nodeType === Node.ELEMENT_NODE) {
          walkAndNormalize(added);
        }
      });
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
}


/**
 * Global Input Guards
 * --------------------------------
 * Enforces clean numeric and date values across all forms.
 */
function initializeInputGuards() {
  const numericInputs = document.querySelectorAll('input[type="number"], input[data-numeric="true"]');
  const blockedNumberKeys = ['e', 'E', '+'];

  numericInputs.forEach((input) => {
    if (!input.hasAttribute('inputmode')) {
      const step = input.getAttribute('step');
      input.setAttribute('inputmode', step && step !== '1' ? 'decimal' : 'numeric');
    }

    input.addEventListener('keydown', (event) => {
      const key = event.key;
      if (blockedNumberKeys.includes(key)) {
        event.preventDefault();
        return;
      }

      const allowNegative = input.dataset.allowNegative === 'true' || input.getAttribute('min') === null;
      if (key === '-' && !allowNegative) {
        event.preventDefault();
      }
    });

    input.addEventListener('input', () => {
      const allowNegative = input.dataset.allowNegative === 'true' || input.getAttribute('min') === null;
      let sanitized = input.value.replace(allowNegative ? /[^0-9.-]/g : /[^0-9.]/g, '');

      if (!allowNegative) {
        sanitized = sanitized.replace(/-/g, '');
      } else {
        sanitized = sanitized.replace(/(?!^)-/g, '');
      }

      if (sanitized.includes('.')) {
        const parts = sanitized.split('.');
        sanitized = `${parts.shift()}.${parts.join('')}`;
      }

      if (sanitized === '.' || sanitized === '-.' || sanitized === '-') {
        sanitized = '';
      }

      if (sanitized !== input.value) {
        input.value = sanitized;
      }
    });
  });

  const autoDateFields = new Set();
  document.querySelectorAll('input').forEach((input) => {
    const marker = `${input.name || ''} ${input.id || ''}`.toLowerCase();
    const looksLikeDateField = /(^|_|-)date($|_|-)|created_at|updated_at|start_date|end_date/.test(marker);
    const excludedDateLikeField = /expiry|month|year|duration/.test(marker);

    if (input.type === 'date') {
      autoDateFields.add(input);
      return;
    }

    if (input.type === 'text' && looksLikeDateField && !excludedDateLikeField) {
      input.type = 'date';
      autoDateFields.add(input);
    }
  });

  autoDateFields.forEach((input) => {
    input.addEventListener('input', () => {
      const value = input.value.trim();
      if (!value) {
        input.setCustomValidity('');
        return;
      }

      const validFormat = /^\d{4}-\d{2}-\d{2}$/.test(value);
      const dateValue = new Date(value);
      const validDate = !Number.isNaN(dateValue.getTime());

      if (!validFormat || !validDate) {
        input.setCustomValidity('Please enter a valid date.');
      } else {
        input.setCustomValidity('');
      }
    });
  });

  const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"]');
  phoneInputs.forEach((input) => {
    input.setAttribute('maxlength', '10');
    input.setAttribute('pattern', '\\d{10}');
    input.setAttribute('inputmode', 'numeric');
    input.addEventListener('input', () => {
      const digits = input.value.replace(/\D+/g, '').slice(0, 10);
      if (digits !== input.value) {
        input.value = digits;
      }
    });
  });

  const ibanInputs = document.querySelectorAll('input[name="iban"]');
  ibanInputs.forEach((input) => {
    input.setAttribute('maxlength', '24');
    input.setAttribute('pattern', 'SA\\d{22}');
    input.addEventListener('input', () => {
      const normalized = input.value.toUpperCase().replace(/\s+/g, '');
      if (normalized !== input.value) {
        input.value = normalized;
      }
    });
  });

  const cardNumberInputs = document.querySelectorAll('input[name="card_number"]');
  cardNumberInputs.forEach((input) => {
    input.setAttribute('maxlength', '14');
    input.setAttribute('pattern', '\\d{14}');
    input.setAttribute('inputmode', 'numeric');
    input.addEventListener('input', () => {
      const digits = input.value.replace(/\D+/g, '').slice(0, 14);
      if (digits !== input.value) {
        input.value = digits;
      }
    });
  });

  const cvvInputs = document.querySelectorAll('input[name="cvv"]');
  cvvInputs.forEach((input) => {
    input.setAttribute('maxlength', '3');
    input.setAttribute('pattern', '\\d{3}');
    input.setAttribute('inputmode', 'numeric');
    input.addEventListener('input', () => {
      const digits = input.value.replace(/\D+/g, '').slice(0, 3);
      if (digits !== input.value) {
        input.value = digits;
      }
    });
  });
}


/**
 * دالة تفعيل/إغلاق القائمة
 * --------------------------------
 * تتحكم في فتح وإغلاق القائمة المنسدلة
 * تغلق القائمة عند النقر خارجها
 */
function initializeMenuToggle() {
  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-menu-toggle]');
    if (toggle) {
      const menu = document.querySelector('[data-menu]');
      if (menu) {
        menu.classList.toggle('open');
        toggle.setAttribute('aria-expanded', menu.classList.contains('open'));
      }
      return;
    }

    const close = event.target.closest('[data-menu-close]');
    if (close) {
      const menu = document.querySelector('[data-menu]');
      if (menu) menu.classList.remove('open');
      return;
    }

    // إغلاق القائمة عند النقر خارجها
    const outsideMenu = document.querySelector('[data-menu]');
    if (outsideMenu && outsideMenu.classList.contains('open') && !event.target.closest('[data-menu]')) {
      outsideMenu.classList.remove('open');
    }

    // نافذة التأكيد
    const confirmButton = event.target.closest('[data-confirm-message]');
    if (confirmButton) {
      const message = confirmButton.dataset.confirmMessage || 'Are you sure?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    }
  });
}


/**
 * دالة أزرار التمرير
 * --------------------------------
 * تتحكم في تمرير بطاقات الصناديق
 * يمين ويسار
 */
function initializeScrollButtons() {
  document.addEventListener('click', (event) => {
    const scrollTrigger = event.target.closest('[data-scroll-target]');
    if (scrollTrigger) {
      const target = document.getElementById(scrollTrigger.dataset.scrollTarget);
      if (target) {
        const direction = scrollTrigger.dataset.direction === 'left' ? -1 : 1;
        target.scrollBy({ left: direction * 240, behavior: 'smooth' });
      }
    }
  });
}


/**
 * دالة التحقق من النماذج
 * --------------------------------
 * تضيف تأثيرات بصرية للحقول
 * وتتحقق من صحة المدخلات
 */
function initializeFormValidation() {
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    const inputs = form.querySelectorAll('.text-input, .select-input, .text-area');
    
    inputs.forEach(input => {
      // إضافة تأثير التركيز
      input.addEventListener('focus', () => {
        input.parentElement.classList.add('field-focused');
      });
      
      input.addEventListener('blur', () => {
        input.parentElement.classList.remove('field-focused');
        // التحقق عند مغادرة الحقل
        if (input.required && !input.value.trim()) {
          input.classList.add('input-error');
        } else {
          input.classList.remove('input-error');
        }
      });
      
      // إزالة الخطأ عند الكتابة
      input.addEventListener('input', () => {
        input.classList.remove('input-error');
      });
    });
  });
}


/**
 * دالة إظهار/إخفاء كلمة المرور
 * --------------------------------
 * تُمكّن المستخدم من رؤية كلمة المرور
 * بالنقر على أيقونة العين
 */
function initializePasswordToggle() {
  document.querySelectorAll('.input-icon').forEach(icon => {
    const input = icon.parentElement.querySelector('input[type="password"]');
    if (input) {
      icon.style.cursor = 'pointer';
      icon.addEventListener('click', () => {
        if (input.type === 'password') {
          input.type = 'text';
          icon.innerHTML = '&#128064;'; // عين مغلقة
        } else {
          input.type = 'password';
          icon.innerHTML = '&#128065;'; // عين مفتوحة
        }
      });
    }
  });
}


/**
 * دالة تأثيرات البطاقات
 * --------------------------------
 * تضيف تأثيرات تحومية وللمس
 * للبطاقات في الواجهة
 */
function initializeCardHoverEffects() {
  const cards = document.querySelectorAll('.fund-card, .feature-card, .stat-box');
  
  cards.forEach(card => {
    // إضافة تأثير اللمس للجوال
    card.addEventListener('touchstart', () => {
      card.classList.add('card-touched');
    }, { passive: true });
    
    card.addEventListener('touchend', () => {
      setTimeout(() => card.classList.remove('card-touched'), 150);
    }, { passive: true });
  });
}


/**
 * إشعارات Toast
 * --------------------------------
 * تعرض رسائل الإشعار وتخفيها تلقائياً
 */
window.addEventListener('load', () => {
  const toast = document.querySelector('[data-live-toast]');
  if (toast) {
    // إضافة تأثير الدخول
    toast.style.animation = 'toastSlideIn 0.4s ease-out';
    
    // إزالة بعد التأخير
    setTimeout(() => {
      toast.style.animation = 'toastFadeOut 0.4s ease-in forwards';
      setTimeout(() => toast.remove(), 400);
    }, 3500);
  }
});


/**
 * دعم التنقل بلوحة المفاتيح
 * --------------------------------
 * إغلاق القائمة بزر Escape
 */
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    const menu = document.querySelector('[data-menu].open');
    if (menu) {
      menu.classList.remove('open');
    }
  }
});


/**
 * Intersection Observer للتأثيرات
 * --------------------------------
 * يضيف تأثيرات حركية عند التمرير
 */
if ('IntersectionObserver' in window) {
  const animateOnScroll = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-in');
        animateOnScroll.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  
  document.querySelectorAll('.card-stack > *, .form-box, .detail-card').forEach(el => {
    el.classList.add('animate-target');
    animateOnScroll.observe(el);
  });
}


/**
 * Fund Search & Filter Functionality
 * --------------------------------
 * يبحث ويفلتر الصناديق في صفحة البحث
 */
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('fundSearch');
  const filterButtons = document.querySelectorAll('.filter-chip');
  const fundsGrid = document.getElementById('fundsGrid');
  const visibleCount = document.getElementById('visibleCount');
  
  if (!searchInput || !fundsGrid) return;
  
  let currentFilter = 'all';
  
  // Search functionality
  searchInput.addEventListener('input', () => {
    filterFunds();
  });
  
  // Filter buttons
  filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      filterButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      filterFunds();
    });
  });
  
  function filterFunds() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const cards = fundsGrid.querySelectorAll('.fund-browse-card');
    let visible = 0;
    
    cards.forEach(card => {
      const name = card.dataset.fundName || '';
      const type = card.dataset.fundType || '';
      const risk = card.dataset.risk || '';
      
      // Check search match
      const matchesSearch = !searchTerm || 
        name.includes(searchTerm) || 
        type.includes(searchTerm) || 
        risk.includes(searchTerm);
      
      // Check filter match
      const matchesFilter = currentFilter === 'all' || risk === currentFilter;
      
      // Show/hide card
      if (matchesSearch && matchesFilter) {
        card.classList.remove('hidden');
        visible++;
      } else {
        card.classList.add('hidden');
      }
    });
    
    // Update count
    if (visibleCount) {
      visibleCount.textContent = visible;
    }
  }
});
