class WatchPage {
    constructor() {
        this.apiBase = 'http://localhost:5000/api';
        this.currentVideo = null;
        this.queue = JSON.parse(localStorage.getItem('queue') || '[]');
        this.currentQualityIndex = 0;
        
        this.initElements();
        this.bindEvents();
        this.setupKeyboardShortcuts();
        this.loadVideoFromURL();
        this.updateQueueDisplay();
    }
    
    initElements() {
        this.searchInput = document.getElementById('searchInput');
        this.searchBtn = document.getElementById('searchBtn');
        this.videoPlayer = document.getElementById('videoPlayer');
        this.videoTitle = document.getElementById('videoTitle');
        this.videoChannel = document.getElementById('videoChannel');
        this.videoViews = document.getElementById('videoViews');
        this.videoLikes = document.getElementById('videoLikes');
        this.videoDate = document.getElementById('videoDate');
        this.videoDescription = document.getElementById('videoDescription');
        this.qualitySelect = document.getElementById('qualitySelect');
        this.captionsSelect = document.getElementById('captionsSelect');
        this.queueList = document.getElementById('queueList');
        this.commentsList = document.getElementById('commentsList');
        this.commentsCount = document.getElementById('commentsCount');
    }
    
    bindEvents() {
        this.searchBtn.addEventListener('click', () => this.performSearch());
        this.searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.performSearch();
        });
        
        this.qualitySelect.addEventListener('change', () => {
            this.changeQuality();
        });
        
        this.captionsSelect.addEventListener('change', () => {
            this.changeCaptions();
        });
        
        this.videoPlayer.addEventListener('ended', () => {
            this.playNext();
        });
    }
    
    performSearch() {
        const query = this.searchInput.value.trim();
        if (!query) return;
        window.location.href = `/?search_query=${encodeURIComponent(query)}`;
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT') return;
            
            switch(e.key) {
                case ' ':
                    e.preventDefault();
                    this.togglePlayPause();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.seek(-10);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.seek(10);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.changeVolume(0.1);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    this.changeVolume(-0.1);
                    break;
                case 'f':
                    e.preventDefault();
                    this.toggleFullscreen();
                    break;
                case 'n':
                    e.preventDefault();
                    this.playNext();
                    break;
            }
        });
    }
    
    loadVideoFromURL() {
        const params = new URLSearchParams(window.location.search);
        const videoId = params.get('v');
        if (videoId) {
            this.loadVideo(videoId);
        }
    }
    
    async loadVideo(videoId) {
        try {
            this.videoTitle.textContent = 'Loading...';
            
            const response = await fetch(`${this.apiBase}/video?id=${videoId}`);
            const videoData = await response.json();
            
            if (videoData.error) {
                throw new Error(videoData.error);
            }
            
            this.currentVideo = { id: videoId, ...videoData };
            this.playVideo(this.currentVideo);
            this.addToQueue(this.currentVideo);
            
        } catch (error) {
            this.showError(`Failed to load video: ${error.message}`);
        }
    }
    
    playVideo(video) {
        document.title = `${video.title} - LighTube`;
        this.videoTitle.textContent = video.title;
        this.videoChannel.textContent = video.channel || '';
        
        // Update metadata
        this.videoViews.textContent = video.views ? `👁 ${this.formatNumber(video.views)} views` : '';
        this.videoLikes.textContent = video.likes ? `👍 ${this.formatNumber(video.likes)}` : '';
        this.videoDate.textContent = video.published ? this.formatDate(video.published) : '';
        this.videoDescription.innerHTML = this.formatDescription(video.description || '');
        this.commentsCount.textContent = video.comments_count ? `(${this.formatNumber(video.comments_count)})` : '';
        
        // Populate quality selector
        this.qualitySelect.innerHTML = '';
        video.formats.forEach((format, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = `${format.quality}p (${format.format.toUpperCase()})`;
            this.qualitySelect.appendChild(option);
        });
        
        // Populate captions selector
        this.captionsSelect.innerHTML = '<option value="">Off</option>';
        if (video.captions) {
            const hideAuto = window.settings && window.settings.get('hideAutoCaptions');
            const manualCaptions = video.captions.filter(c => !c.auto);
            const autoCaptions = video.captions.filter(c => c.auto);
            
            // Add manual captions
            if (manualCaptions.length > 0) {
                const manualGroup = document.createElement('optgroup');
                manualGroup.label = 'Manual Captions';
                manualCaptions.forEach((caption, index) => {
                    const realIndex = video.captions.indexOf(caption);
                    const option = document.createElement('option');
                    option.value = realIndex;
                    option.textContent = this.getLanguageName(caption.language);
                    manualGroup.appendChild(option);
                });
                this.captionsSelect.appendChild(manualGroup);
            }
            
            // Add auto captions if not hidden
            if (!hideAuto && autoCaptions.length > 0) {
                const autoGroup = document.createElement('optgroup');
                autoGroup.label = 'Auto-Generated';
                autoCaptions.forEach((caption, index) => {
                    const realIndex = video.captions.indexOf(caption);
                    const option = document.createElement('option');
                    option.value = realIndex;
                    option.textContent = this.getLanguageName(caption.language);
                    autoGroup.appendChild(option);
                });
                this.captionsSelect.appendChild(autoGroup);
            }
        }
        
        this.currentQualityIndex = 0;
        this.loadVideoSource();
        
        // Load comments based on settings
        if (window.settings && window.settings.get('autoLoadComments')) {
            this.loadComments(this.currentVideo.id);
        } else {
            this.showLoadCommentsButton();
        }
    }
    
    loadVideoSource() {
        const format = this.currentVideo.formats[this.currentQualityIndex];
        if (format) {
            const currentTime = this.videoPlayer.currentTime;
            this.videoPlayer.src = format.url;
            this.videoPlayer.currentTime = currentTime;
            this.videoPlayer.load();
        }
    }
    
    changeQuality() {
        this.currentQualityIndex = parseInt(this.qualitySelect.value);
        this.loadVideoSource();
    }
    
    changeCaptions() {
        const captionIndex = this.captionsSelect.value;
        
        // Remove existing tracks
        const tracks = this.videoPlayer.querySelectorAll('track');
        tracks.forEach(track => track.remove());
        
        if (captionIndex !== '' && this.currentVideo.captions) {
            const caption = this.currentVideo.captions[parseInt(captionIndex)];
            const proxyUrl = `${this.apiBase}/captions?url=${encodeURIComponent(caption.url)}`;
            console.log('Loading caption from:', proxyUrl);
            
            const track = document.createElement('track');
            track.kind = 'subtitles';
            track.src = proxyUrl;
            track.srclang = caption.language;
            track.label = this.getLanguageName(caption.language);
            
            track.addEventListener('load', () => {
                console.log('Caption loaded successfully');
                track.track.mode = 'showing';
            });
            
            track.addEventListener('error', (e) => {
                console.error('Caption loading error. Track src:', track.src);
                console.error('Original caption URL:', caption.url);
            });
            
            this.videoPlayer.appendChild(track);
            
            // Fallback: try to enable after timeout
            setTimeout(() => {
                if (this.videoPlayer.textTracks.length > 0) {
                    this.videoPlayer.textTracks[0].mode = 'showing';
                }
            }, 500);
        }
    }
    
    getLanguageName(code) {
        const languages = {
            'en': 'English',
            'es': 'Spanish',
            'fr': 'French',
            'de': 'German',
            'it': 'Italian',
            'pt': 'Portuguese',
            'ru': 'Russian',
            'ja': 'Japanese',
            'ko': 'Korean',
            'zh': 'Chinese',
            'ar': 'Arabic',
            'hi': 'Hindi'
        };
        return languages[code] || code.toUpperCase();
    }
    
    addToQueue(video) {
        // Remove if already in queue
        this.queue = this.queue.filter(v => v.id !== video.id);
        this.queue.push(video);
        this.saveQueue();
        this.updateQueueDisplay();
    }
    
    updateQueueDisplay() {
        this.queueList.innerHTML = '';
        
        this.queue.forEach((video, index) => {
            const item = document.createElement('div');
            item.className = 'queue-item';
            item.innerHTML = `
                <img src="${video.thumbnail || ''}" alt="${video.title}">
                <div class="queue-item-info">
                    <div class="queue-item-title">${this.escapeHtml(video.title)}</div>
                    <div class="queue-item-channel">${this.escapeHtml(video.channel || '')}</div>
                </div>
            `;
            
            item.addEventListener('click', () => {
                window.location.href = `/watch.html?v=${video.id}`;
            });
            
            this.queueList.appendChild(item);
        });
    }
    
    playNext() {
        if (this.queue.length > 1) {
            // Remove current video and get next
            const currentIndex = this.queue.findIndex(v => v.id === this.currentVideo.id);
            if (currentIndex !== -1 && currentIndex < this.queue.length - 1) {
                const nextVideo = this.queue[currentIndex + 1];
                window.location.href = `/watch.html?v=${nextVideo.id}`;
            }
        }
    }
    
    saveQueue() {
        localStorage.setItem('queue', JSON.stringify(this.queue));
    }
    
    togglePlayPause() {
        if (this.videoPlayer.paused) {
            this.videoPlayer.play();
        } else {
            this.videoPlayer.pause();
        }
    }
    
    seek(seconds) {
        this.videoPlayer.currentTime += seconds;
    }
    
    changeVolume(delta) {
        const newVolume = Math.max(0, Math.min(1, this.videoPlayer.volume + delta));
        this.videoPlayer.volume = newVolume;
    }
    
    toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            this.videoPlayer.requestFullscreen();
        }
    }
    
    showError(message) {
        const existing = document.querySelector('.error');
        if (existing) existing.remove();
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error';
        errorDiv.textContent = message;
        
        document.querySelector('.video-section').insertBefore(errorDiv, document.querySelector('.video-container'));
    }
    
    showLoadCommentsButton() {
        this.commentsList.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <button id="loadCommentsBtn" style="padding: 10px 20px; background: #ff6b6b; color: white; border: none; border-radius: 4px; cursor: pointer;">Load Comments</button>
            </div>
        `;
        
        document.getElementById('loadCommentsBtn').addEventListener('click', () => {
            this.loadComments(this.currentVideo.id);
        });
    }
    
    async loadComments(videoId) {
        this.commentsList.innerHTML = '<div style="text-align: center; padding: 20px;">Loading comments...</div>';
        
        try {
            const response = await fetch(`${this.apiBase}/comments?id=${videoId}`);
            const data = await response.json();
            
            if (data.comments) {
                this.displayComments(data.comments);
            } else {
                this.commentsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">Comments are disabled for this video</div>';
            }
        } catch (error) {
            console.error('Failed to load comments:', error);
            this.commentsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #ff6b6b;">Failed to load comments</div>';
        }
    }
    
    displayComments(comments) {
        this.commentsList.innerHTML = '';
        
        comments.forEach(comment => {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment';
            const showAvatars = !window.settings || window.settings.get('showProfilePictures');
            commentDiv.innerHTML = `
                ${showAvatars ? `<img src="${comment.avatar}" alt="${comment.author}" class="comment-avatar">` : ''}
                <div class="comment-content">
                    <div class="comment-author">${this.escapeHtml(comment.author)}</div>
                    <div class="comment-text">${this.escapeHtml(comment.text)}</div>
                    <div class="comment-meta">
                        <span>👍 ${comment.likes}</span>
                        <span>${this.formatDate(comment.published)}</span>
                    </div>
                </div>
            `;
            
            this.commentsList.appendChild(commentDiv);
        });
    }
    
    formatNumber(num) {
        const n = parseInt(num);
        if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
        return n.toString();
    }
    
    formatDate(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        
        if (days === 0) return 'Today';
        if (days === 1) return '1 day ago';
        if (days < 30) return `${days} days ago`;
        if (days < 365) return `${Math.floor(days / 30)} months ago`;
        return `${Math.floor(days / 365)} years ago`;
    }
    
    formatDescription(text) {
        if (!text) return '';
        // Decode HTML entities and convert line breaks to <br>
        return this.decodeHtml(text).replace(/\n/g, '<br>');
    }
    
    decodeHtml(text) {
        const div = document.createElement('div');
        div.innerHTML = text;
        return div.textContent || div.innerText || '';
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new WatchPage();
});