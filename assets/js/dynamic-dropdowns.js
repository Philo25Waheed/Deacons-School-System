// Dynamic Stage -> Grade -> Class Cascading Dropdowns

function initDynamicDropdowns(stageSelectId, gradeSelectId, classSelectId) {
    const stageSelect = document.getElementById(stageSelectId);
    const gradeSelect = document.getElementById(gradeSelectId);
    const classSelect = document.getElementById(classSelectId);

    if (!stageSelect || !gradeSelect || !classSelect) return;

    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '../';

    stageSelect.addEventListener('change', function() {
        const stageId = this.value;
        gradeSelect.innerHTML = '<option value="">اختر الصف...</option>';
        classSelect.innerHTML = '<option value="">اختر الفصل...</option>';

        if (!stageId) return;

        fetch(`${baseUrl}api/get_grades.php?stage_id=${stageId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success' && res.data) {
                    res.data.forEach(grade => {
                        const opt = document.createElement('option');
                        opt.value = grade.id;
                        opt.textContent = grade.name_ar;
                        gradeSelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Error fetching grades:', err));
    });

    gradeSelect.addEventListener('change', function() {
        const gradeId = this.value;
        classSelect.innerHTML = '<option value="">اختر الفصل...</option>';

        if (!gradeId) return;

        fetch(`${baseUrl}api/get_classes.php?grade_id=${gradeId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success' && res.data) {
                    res.data.forEach(cls => {
                        const opt = document.createElement('option');
                        opt.value = cls.id;
                        opt.textContent = cls.name_ar;
                        classSelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Error fetching classes:', err));
    });
}
