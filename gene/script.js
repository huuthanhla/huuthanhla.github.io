// --- 1. QUẢN LÝ DỮ LIỆU ---
// Biến defaultData đã được tải từ file data.js
let genealogyData = null;
let flatMap = new Map();
let currentEditId = null;

let modalHistory, modalSpouse, modalChild, modalEdit;

document.addEventListener('DOMContentLoaded', function() {
    modalHistory = new bootstrap.Modal(document.getElementById('historyModal'));
    modalSpouse = new bootstrap.Modal(document.getElementById('spouseModal'));
    modalChild = new bootstrap.Modal(document.getElementById('childModal'));
    modalEdit = new bootstrap.Modal(document.getElementById('editModal')); // Modal Sửa
    
    init();
});

// Hàm khởi tạo dữ liệu
function init() {
    // 1. Ưu tiên lấy từ Local Storage (Dữ liệu người dùng đang làm việc)
    const storedData = localStorage.getItem('genealogyData');
    if (storedData) {
        genealogyData = JSON.parse(storedData);
    } else {
        // 2. Nếu không có, dùng dữ liệu gốc từ data.js
        genealogyData = JSON.parse(JSON.stringify(defaultData));
    }

    // Cập nhật địa chỉ header
    if(genealogyData.thong_tin_chung && genealogyData.thong_tin_chung.dia_chi_moi) {
        document.getElementById("newAddress").innerText = genealogyData.thong_tin_chung.dia_chi_moi;
    }

    // Đánh chỉ mục
    flatMap.clear();
    // Hỗ trợ cả key cũ 'cay_pha_he' và key mới 'genealogy'
    let rootData = genealogyData.genealogy || genealogyData.cay_pha_he;
    indexData(rootData);
}

// Hàm lưu dữ liệu vào LocalStorage (Gọi hàm này mỗi khi thay đổi dữ liệu)
function saveData() {
    localStorage.setItem('genealogyData', JSON.stringify(genealogyData));
}

// Hàm đánh chỉ mục ID và lưu ID cha để tiện cho việc xóa và hiển thị cha mẹ
function indexData(members, parentId = 'root') {
    if (!members) return;
    members.forEach((member, index) => {
        // Nếu chưa có ID thì tạo mới
        if (!member._id) {
            member._id = parentId + '_' + index + '_' + Math.random().toString(36).substr(2, 5);
        }
        // Lưu tham chiếu ID cha vào object
        member._parentId = parentId;

        flatMap.set(member._id, member);
        
        // Hỗ trợ cả 'children' và 'con_cai'
        let children = member.children || member.con_cai;
        if (children && children.length > 0) {
            indexData(children, member._id);
        }
    });
}

// --- 2. IMPORT / EXPORT / RESET ---

// Hàm Import JSON từ File
function importJsonFile() {
    const fileInput = document.getElementById('jsonFile');
    const file = fileInput.files[0];

    if (!file) {
        alert("Vui lòng chọn file JSON để import!");
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const json = JSON.parse(e.target.result);
            
            // Validate sơ bộ
            if (!json.genealogy && !json.cay_pha_he) {
                throw new Error("File không đúng định dạng phả hệ (thiếu trường genealogy hoặc cay_pha_he)");
            }
            
            genealogyData = json;
            saveData(); // Lưu ngay vào LocalStorage
            init(); // Re-index
            searchMember(); // Clear kết quả tìm kiếm cũ
            alert("Import dữ liệu thành công!");
            
            // Xóa input file
            fileInput.value = '';
        } catch (err) {
            alert("Lỗi khi đọc file: " + err.message);
        }
    };
    reader.readAsText(file);
}

// Hàm Reset về dữ liệu gốc
function resetData() {
    if(confirm("Bạn có chắc chắn muốn xóa mọi thay đổi và quay về dữ liệu gốc ban đầu?")) {
        localStorage.removeItem('genealogyData');
        init();
        searchMember();
        alert("Đã reset về dữ liệu gốc.");
    }
}

// Hàm Backup (Tải về)
function downloadJSON() {
    const cleanData = JSON.parse(JSON.stringify(genealogyData));
    
    // Hàm đệ quy xóa các trường tạm (_id, _parentId)
    function removeTempIds(list) {
        if(!list) return;
        list.forEach(item => {
            delete item._id;
            delete item._parentId;
            let children = item.children || item.con_cai;
            if (children) removeTempIds(children);
        });
    }
    let root = cleanData.genealogy || cleanData.cay_pha_he;
    removeTempIds(root);

    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(cleanData, null, 4));
    const downloadAnchorNode = document.createElement('a');
    downloadAnchorNode.setAttribute("href", dataStr);

    const timestamp = new Date().toLocaleString('sv-SE').replace(/[-: ]/g,'')
    // .replace('T','_')
    .replace(/(\d{8})(\d{6})/,'$1_$2');;
    
    downloadAnchorNode.setAttribute("download", "GiaPha_LaHuu_CapNhat_" + timestamp + ".json");
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();
}

// --- 3. HÀM XỬ LÝ DATE PICKER ---
function updateDateInput(textId, pickerId) {
    const picker = document.getElementById(pickerId);
    const textInput = document.getElementById(textId);
    if (picker.value) {
        const parts = picker.value.split('-'); // [yyyy, mm, dd]
        if (parts.length === 3) {
            textInput.value = `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
    }
}

// --- 4. TÌM KIẾM VÀ HIỂN THỊ ---
function formatSpouseData(spouseData) {
    if (!spouseData) return null;
    
    if (Array.isArray(spouseData)) {
        return spouseData.map(item => {
            if (typeof item === 'object' && item.ho_ten) {
                return `${item.ho_ten} ${item.ngay_sinh ? `<small class="text-muted">(sn: ${item.ngay_sinh})</small>` : ''}`;
            }
            return item; 
        }).join(", ");
    }
    
    if (typeof spouseData === 'object' && spouseData.ho_ten) {
        return `${spouseData.ho_ten} ${spouseData.ngay_sinh ? `<small class="text-muted">(sn: ${spouseData.ngay_sinh})</small>` : ''}`;
    }
    
    return spouseData;
}

function formatChildren(childrenArray) {
    if (!childrenArray || childrenArray.length === 0) return null;
    return childrenArray.map(child => {
        return `${child.ho_ten} ${child.ngay_sinh ? `(${child.ngay_sinh})` : ''}`;
    }).join(", ");
}

const noAccent = str => str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g,'d').replace(/Đ/g,'D');

function searchMember() {
    const query = document.getElementById('searchInput').value.toLowerCase().trim();
    const resultsArea = document.getElementById('resultsArea');
    
    if (!query) {
        resultsArea.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                <p>Vui lòng nhập tên để tìm kiếm.</p>
            </div>`;
        return;
    }

    const results = [];
    flatMap.forEach(member => {
        let searchStr = noAccent(member.ho_ten + ' ' + member.vo + ' ' + member.chong).toLowerCase();
        if (searchStr.includes(noAccent(query))) {
            results.push(member);
        }
    });

    if (results.length === 0) {
        resultsArea.innerHTML = `
            <div class="alert alert-warning text-center" role="alert">
                <i class="fas fa-times-circle me-2"></i> Không tìm thấy thành viên nào có tên "${document.getElementById('searchInput').value}".
            </div>`;
    } else {
        let html = '<div class="row g-4">';
        results.forEach(mem => {

            // --- XỬ LÝ VỢ/CHỒNG ---
            let spouseHtml = '';
            if (mem.vo) spouseHtml = `<div class="mb-1">Vợ: ${formatSpouseData(mem.vo)}</div>`;
            else if (mem.chong) spouseHtml = `<div class="mb-1">Chồng: ${formatSpouseData(mem.chong)}</div>`;
            else spouseHtml = ``;

            // --- XỬ LÝ CHA/MẸ (BOX RIÊNG) ---
            let parentHtml = '';
            if (mem._parentId && mem._parentId !== 'root') {
                const parent = flatMap.get(mem._parentId);
                if (parent) {
                    parentHtml = `
                        <div class="children-box mb-2" style="background-color: #f8f9fa; border-left: 4px solid #0d6efd;">
                            <small class="text-primary fw-bold d-block mb-1">
                                <i class="fas fa-arrow-up me-1"></i>Con ông:
                            </small>
                            <span class="fw-bold text-dark fs-6">${parent.ho_ten}</span>
                        </div>`;
                }
            }

            // --- XỬ LÝ CON CÁI ---
            let children = mem.children || mem.con_cai;
            const childrenStr = formatChildren(children);
            const childrenHtml = childrenStr 
                ? `<div class="children-box mt-2">
                    <small class="text-danger fw-bold d-block mb-1"><i class="fas fa-child me-1"></i>Các con:</small>
                    <span class="fst-italic">${childrenStr}</span>
                   </div>` 
                : '';
            
            const birthInfo = mem.ngay_sinh ? `<span class="badge bg-secondary ms-2"><i class="fas fa-birthday-cake me-1"></i>${mem.ngay_sinh}</span>` : '';
            const gen = mem.gen || mem.doi;

            html += `
                <div class="col-12">
                    <div class="card member-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
                                <div>
                                    <div class="member-name">
                                        ${mem.ho_ten}
                                        ${birthInfo}
                                    </div>
                                    ${mem.ten_khac ? `<small class="text-muted">(${mem.ten_khac})</small>` : ''}
                                    <span>${spouseHtml}</span>   
                                </div> 
                                <span class="badge badge-gen rounded-pill">F${gen}</span>
                            </div>

                            <div class="mb-3">                                                            
                                ${parentHtml} ${mem.chuc_vu ? `<div class="mb-1"><span class="label-text">Chức vụ:</span> ${mem.chuc_vu}</div>` : ''}                                
                                ${mem.ghi_chu ? `<div class="mb-1"><span class="label-text">Ghi chú:</span> ${mem.ghi_chu}</div>` : ''}
                                ${childrenHtml}
                            </div>

                            <div class="d-flex gap-2 pt-2 border-top flex-wrap">
                                <button class="btn btn-outline-primary btn-sm flex-fill" onclick="openSpouseModal('${mem._id}')">
                                    <i class="fas fa-user-plus me-1"></i>Thêm Vợ/Chồng
                                </button>
                                <button class="btn btn-outline-success btn-sm flex-fill" onclick="openChildModal('${mem._id}')">
                                    <i class="fas fa-baby me-1"></i>Thêm Con
                                </button>
                                <button class="btn btn-outline-warning btn-sm flex-fill" onclick="openEditModal('${mem._id}')">
                                    <i class="fas fa-edit me-1"></i>Sửa
                                </button>
                                <button class="btn btn-outline-danger btn-sm flex-fill" onclick="deleteMember('${mem._id}')">
                                    <i class="fas fa-trash-alt me-1"></i>Xóa
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        resultsArea.innerHTML = html;
    }
}

document.getElementById("searchInput").addEventListener("keyup", function(event) {
    if (event.key === "Enter") searchMember();
});

// --- 5. MODAL LOGIC (ADD/EDIT/DELETE) ---
function openSpouseModal(id) {
    currentEditId = id;
    document.getElementById('spouseName').value = '';
    document.getElementById('spouseDob').value = '';
    document.getElementById('spouseDobPicker').value = ''; 
    modalSpouse.show();
}

function openChildModal(id) {
    currentEditId = id;
    document.getElementById('childName').value = '';
    document.getElementById('childDob').value = '';
    document.getElementById('childDobPicker').value = '';
    document.getElementById('childNote').value = '';
    modalChild.show();
}

function confirmAddSpouse() {
    if (!currentEditId) return;
    const name = document.getElementById('spouseName').value.trim();
    const dob = document.getElementById('spouseDob').value.trim();
    const role = document.getElementById('spouseRole').value; 

    if (!name) { alert("Vui lòng nhập tên!"); return; }

    const newSpouse = { ho_ten: name };
    if (dob) newSpouse.ngay_sinh = dob;

    const member = flatMap.get(currentEditId);
    if (member) {
        if (!member[role]) {
            member[role] = newSpouse;
        } else {
            if (Array.isArray(member[role])) {
                member[role].push(newSpouse);
            } else {
                member[role] = [member[role], newSpouse];
            }
        }
        
        saveData(); 
        alert("Đã thêm thành công!");
        modalSpouse.hide();
        searchMember();
    }
}

function confirmAddChild() {
    if (!currentEditId) return;
    const name = document.getElementById('childName').value.trim();
    const dob = document.getElementById('childDob').value.trim();
    const gender = document.getElementById('childGender').value;
    const note = document.getElementById('childNote').value.trim();

    if (!name) { alert("Vui lòng nhập tên con!"); return; }

    const parent = flatMap.get(currentEditId);
    if (parent) {
        // Chuẩn hóa tên trường con cái (dùng 'children' làm chuẩn)
        if (!parent.children) parent.children = [];
        // Nếu dữ liệu cũ dùng 'con_cai', ta vẫn push vào children cho nhất quán về sau
        
        const parentGen = parent.gen || parent.doi || 0;
        
        const newChild = {
            "gen": parentGen + 1,
            "ho_ten": name,
            "gioi_tinh": gender
        };
        if (dob) newChild.ngay_sinh = dob;
        if (note) newChild.ghi_chu = note;
        
        parent.children.push(newChild);
        
        saveData(); // Lưu thay đổi
        init(); // Re-index vì có node mới
        alert("Đã thêm con thành công!");
        modalChild.hide();
        searchMember();
    }
}

function openEditModal(id) {
    currentEditId = id;
    const member = flatMap.get(id);
    if (!member) return;

    document.getElementById('editName').value = member.ho_ten;
    document.getElementById('editDob').value = member.ngay_sinh || '';
    document.getElementById('editDobPicker').value = ''; 
    document.getElementById('editGender').value = member.gioi_tinh || 'Nam';

    modalEdit.show();
}

function confirmEditMember() {
    if (!currentEditId) return;
    const member = flatMap.get(currentEditId);
    
    const newDob = document.getElementById('editDob').value.trim();
    const newGender = document.getElementById('editGender').value;

    if (newDob) member.ngay_sinh = newDob; else delete member.ngay_sinh;
    member.gioi_tinh = newGender;

    saveData(); 
    alert("Cập nhật thành công!");
    modalEdit.hide();
    searchMember();
}

function deleteMember(id) {
    const member = flatMap.get(id);
    if (!member) return;

    let children = member.children || member.con_cai;
    if (children && children.length > 0) {
        alert("Không thể xóa! Thành viên này đang có danh sách con.\nVui lòng xóa các con trước.");
        return;
    }

    if (!confirm(`Bạn có chắc chắn muốn xóa thành viên "${member.ho_ten}" không?`)) {
        return;
    }

    const parentId = member._parentId;
    if (parentId === 'root') {
        let rootList = genealogyData.genealogy || genealogyData.cay_pha_he;
        const rootIndex = rootList.findIndex(m => m._id === id);
        if (rootIndex > -1) rootList.splice(rootIndex, 1);
    } else {
        const parent = flatMap.get(parentId);
        let pChildren = parent.children || parent.con_cai;
        if (pChildren) {
            const childIndex = pChildren.findIndex(c => c._id === id);
            if (childIndex > -1) pChildren.splice(childIndex, 1);
        }
    }

    saveData(); 
    alert("Đã xóa thành công!");
    init(); 
    searchMember(); 
}

function showHistoryModal() {
    modalHistory.show();
}