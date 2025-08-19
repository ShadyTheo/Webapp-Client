// Application State
let currentUser = null;
let currentLibrary = null;
let currentTopic = null;

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    checkTheme();
    checkAuth();
});

// API Helper Functions
async function apiRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        },
        ...options
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Request failed');
    }
    
    return response.json();
}

async function apiUpload(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        body: formData
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Upload failed');
    }
    
    return response.json();
}

// Authentication
async function checkAuth() {
    try {
        const result = await apiRequest('/api/auth/current');
        currentUser = result.user;
        showPage('galleryPage');
        updateUI();
        await loadLibraries();
    } catch (error) {
        showPage('loginPage');
    }
}

async function login(username, password) {
    try {
        const result = await apiRequest('/api/auth/login', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
        
        currentUser = result.user;
        showPage('galleryPage');
        updateUI();
        await loadLibraries();
        return true;
    } catch (error) {
        alert(error.message);
        return false;
    }
}

async function logout() {
    try {
        await apiRequest('/api/auth/logout', { method: 'POST' });
    } catch (error) {
        console.error('Logout error:', error);
    }
    
    currentUser = null;
    currentLibrary = null;
    currentTopic = null;
    showPage('loginPage');
    updateUI();
}

// Page Navigation
function showPage(pageId) {
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    document.getElementById(pageId).classList.add('active');
}

// UI Updates
function updateUI() {
    const adminBtn = document.getElementById('adminBtn');
    if (currentUser && currentUser.role === 'admin') {
        adminBtn.classList.add('visible');
    } else {
        adminBtn.classList.remove('visible');
    }
}

// Library Management
async function loadLibraries() {
    try {
        const libraries = await apiRequest('/api/libraries');
        
        const librarySelect = document.getElementById('librarySelect');
        const uploadLibrary = document.getElementById('uploadLibrary');
        
        librarySelect.innerHTML = '<option value="">Select Library</option>';
        uploadLibrary.innerHTML = '<option value="">Select Library</option>';
        
        libraries.forEach(library => {
            const option1 = document.createElement('option');
            option1.value = library.id;
            option1.textContent = library.name;
            librarySelect.appendChild(option1);
            
            const option2 = document.createElement('option');
            option2.value = library.id;
            option2.textContent = library.name;
            uploadLibrary.appendChild(option2);
        });
        
        await updateLibraryList();
    } catch (error) {
        console.error('Failed to load libraries:', error);
    }
}

async function createLibrary(name, description) {
    try {
        await apiRequest('/api/libraries', {
            method: 'POST',
            body: JSON.stringify({ name, description })
        });
        await loadLibraries();
    } catch (error) {
        alert(error.message);
    }
}

async function updateLibraryList() {
    if (currentUser?.role !== 'admin') return;
    
    try {
        const libraries = await apiRequest('/api/libraries');
        const libraryList = document.getElementById('libraryList');
        libraryList.innerHTML = '';
        
        libraries.forEach(library => {
            const item = document.createElement('div');
            item.className = 'library-item';
            item.innerHTML = `
                <div>
                    <strong>${library.name}</strong>
                    <br><small>${library.description}</small>
                </div>
                <button class="delete-btn" onclick="deleteLibrary(${library.id})">Delete</button>
            `;
            libraryList.appendChild(item);
        });
    } catch (error) {
        console.error('Failed to update library list:', error);
    }
}

async function deleteLibrary(libraryId) {
    if (!confirm('Are you sure you want to delete this library? All media will be removed.')) {
        return;
    }
    
    try {
        await apiRequest(`/api/libraries?id=${libraryId}`, { method: 'DELETE' });
        await loadLibraries();
        if (currentLibrary == libraryId) {
            currentLibrary = null;
            currentTopic = null;
            document.getElementById('gallery').innerHTML = '';
            document.getElementById('topicNav').innerHTML = '';
        }
    } catch (error) {
        alert(error.message);
    }
}

// User Management
async function createUser(username, password, role) {
    try {
        await apiRequest('/api/users', {
            method: 'POST',
            body: JSON.stringify({ username, password, role })
        });
        await updateUserList();
        return true;
    } catch (error) {
        alert(error.message);
        return false;
    }
}

async function updateUserList() {
    if (currentUser?.role !== 'admin') return;
    
    try {
        const users = await apiRequest('/api/users');
        const userList = document.getElementById('userList');
        userList.innerHTML = '';
        
        users.forEach(user => {
            const item = document.createElement('div');
            item.className = 'user-item';
            item.innerHTML = `
                <div>
                    <strong>${user.username}</strong> (${user.role})
                </div>
                ${user.id !== 1 ? `<button class="delete-btn" onclick="deleteUser(${user.id})">Delete</button>` : ''}
            `;
            userList.appendChild(item);
        });
    } catch (error) {
        console.error('Failed to update user list:', error);
    }
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) {
        return;
    }
    
    try {
        await apiRequest(`/api/users?id=${userId}`, { method: 'DELETE' });
        await updateUserList();
    } catch (error) {
        alert(error.message);
    }
}

// Media Management
async function uploadMedia(files, libraryId, topic) {
    if (!libraryId || !topic) {
        alert('Please select a library and enter a topic');
        return;
    }
    
    const formData = new FormData();
    Array.from(files).forEach(file => {
        formData.append('files[]', file);
    });
    formData.append('libraryId', libraryId);
    formData.append('topic', topic);
    
    try {
        await apiUpload('/api/media', formData);
        if (currentLibrary == libraryId) {
            await loadGallery();
        }
        alert('Files uploaded successfully!');
    } catch (error) {
        alert(error.message);
    }
}

// Gallery Display
async function loadGallery() {
    if (!currentLibrary) return;
    
    try {
        const media = await apiRequest(`/api/media?library=${currentLibrary}`);
        const topics = await apiRequest(`/api/media/topics?library=${currentLibrary}`);
        
        updateTopicNavigation(topics);
        displayMedia(media);
    } catch (error) {
        console.error('Failed to load gallery:', error);
    }
}

function updateTopicNavigation(topics) {
    const topicNav = document.getElementById('topicNav');
    topicNav.innerHTML = '';
    
    const allBtn = document.createElement('div');
    allBtn.className = 'topic-nav-item';
    allBtn.textContent = 'All';
    allBtn.onclick = () => selectTopic(null);
    if (!currentTopic) allBtn.classList.add('active');
    topicNav.appendChild(allBtn);
    
    topics.forEach(topic => {
        const btn = document.createElement('div');
        btn.className = 'topic-nav-item';
        btn.textContent = topic;
        btn.onclick = () => selectTopic(topic);
        if (currentTopic === topic) btn.classList.add('active');
        topicNav.appendChild(btn);
    });
}

async function selectTopic(topic) {
    currentTopic = topic;
    await loadGallery();
}

function displayMedia(media) {
    const gallery = document.getElementById('gallery');
    gallery.innerHTML = '';
    
    const filteredMedia = currentTopic ? 
        media.filter(m => m.topic === currentTopic) : media;
    
    filteredMedia.forEach(item => {
        const mediaElement = document.createElement('div');
        mediaElement.className = 'media-item';
        
        const content = item.type === 'video' ? 
            `<video src="${item.url}" controls></video>` :
            `<img src="${item.url}" alt="${item.name}">`;
        
        mediaElement.innerHTML = `
            ${content}
            <div class="media-overlay">
                <span>${item.topic}</span>
                <button class="download-btn" onclick="downloadMedia(${item.id}, '${item.name}')">
                    Download
                </button>
            </div>
        `;
        
        mediaElement.onclick = (e) => {
            if (!e.target.classList.contains('download-btn')) {
                showModal(item);
            }
        };
        
        gallery.appendChild(mediaElement);
    });
}

// Download functionality
function downloadMedia(mediaId, filename) {
    window.open(`/api/download?id=${mediaId}`, '_blank');
}

// Modal for image preview
function showModal(media) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'block';
    
    const content = media.type === 'video' ?
        `<video src="${media.url}" controls autoplay></video>` :
        `<img src="${media.url}" alt="${media.name}">`;
    
    modal.innerHTML = `
        <div class="modal-content">
            ${content}
            <span class="close">&times;</span>
        </div>
    `;
    
    modal.onclick = (e) => {
        if (e.target === modal || e.target.classList.contains('close')) {
            modal.remove();
        }
    };
    
    document.body.appendChild(modal);
}

// Theme Management
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.textContent = newTheme === 'light' ? '🌞' : '🌙';
}

function checkTheme() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.textContent = savedTheme === 'light' ? '🌞' : '🌙';
}

// Event Listeners
function initializeEventListeners() {
    // Login Form
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        if (await login(username, password)) {
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
        }
    });
    
    // Navigation
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('adminBtn').addEventListener('click', async () => {
        showPage('adminPage');
        await updateUserList();
    });
    document.getElementById('backToGallery').addEventListener('click', () => {
        showPage('galleryPage');
    });
    
    // Theme Toggle
    document.getElementById('themeToggle').addEventListener('click', toggleTheme);
    
    // Library Selection
    document.getElementById('librarySelect').addEventListener('change', async function(e) {
        currentLibrary = e.target.value;
        currentTopic = null;
        if (currentLibrary) {
            await loadGallery();
        } else {
            document.getElementById('gallery').innerHTML = '';
            document.getElementById('topicNav').innerHTML = '';
        }
    });
    
    // Admin Forms
    document.getElementById('addUserForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const username = document.getElementById('newUsername').value;
        const password = document.getElementById('newPassword').value;
        const role = document.getElementById('userRole').value;
        
        if (await createUser(username, password, role)) {
            this.reset();
        }
    });
    
    document.getElementById('createLibraryForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const name = document.getElementById('libraryName').value;
        const description = document.getElementById('libraryDescription').value;
        
        await createLibrary(name, description);
        this.reset();
    });
    
    // Media Upload
    document.getElementById('uploadBtn').addEventListener('click', async function() {
        const files = document.getElementById('mediaUpload').files;
        const libraryId = document.getElementById('uploadLibrary').value;
        const topic = document.getElementById('uploadTopic').value;
        
        if (files.length > 0) {
            await uploadMedia(files, libraryId, topic);
            document.getElementById('mediaUpload').value = '';
            document.getElementById('uploadTopic').value = '';
        }
    });
}