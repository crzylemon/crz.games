class TrailerPlayer {
    constructor(container, videoUrl) {
        this.container = container;
        this.videoUrl = videoUrl;
        this.init();
    }
    
    init() {
        this.createPlayer();
        this.setupControls();
        this.setupKeyboardShortcuts();
    }
    
    createPlayer() {
        this.container.innerHTML = `
            <div class="trailer-player">
                <video class="trailer-video">
                    <source src="${this.videoUrl}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <div class="trailer-controls">
                    <button class="play-pause-btn">▶</button>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="time-display">
                        <span class="current-time">0:00</span> / <span class="duration">0:00</span>
                    </div>
                    <button class="volume-btn">♪</button>
                    <button class="fullscreen-btn">⛶</button>
                </div>
            </div>
        `;
        
        this.video = this.container.querySelector('.trailer-video');
        this.playPauseBtn = this.container.querySelector('.play-pause-btn');
        this.progressBar = this.container.querySelector('.progress-bar');
        this.progressFill = this.container.querySelector('.progress-fill');
        this.currentTimeSpan = this.container.querySelector('.current-time');
        this.durationSpan = this.container.querySelector('.duration');
        this.volumeBtn = this.container.querySelector('.volume-btn');
        this.fullscreenBtn = this.container.querySelector('.fullscreen-btn');
    }
    
    setupControls() {
        this.playPauseBtn.addEventListener('click', () => this.togglePlayPause());
        this.volumeBtn.addEventListener('click', () => this.toggleMute());
        this.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
        
        this.video.addEventListener('timeupdate', () => this.updateProgress());
        this.video.addEventListener('loadedmetadata', () => this.updateDuration());
        this.video.addEventListener('play', () => this.playPauseBtn.textContent = '⏸');
        this.video.addEventListener('pause', () => this.playPauseBtn.textContent = '▶');
        
        this.progressBar.addEventListener('click', (e) => this.seek(e));
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch(e.key) {
                case ' ':
                    e.preventDefault();
                    this.togglePlayPause();
                    break;
                case 'ArrowLeft':
                    this.video.currentTime -= 10;
                    break;
                case 'ArrowRight':
                    this.video.currentTime += 10;
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.video.currentTime += 60;
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    this.video.currentTime -= 60;
                    break;
                case 'f':
                    this.toggleFullscreen();
                    break;
                case 'm':
                    this.toggleMute();
                    break;
            }
        });
    }
    
    togglePlayPause() {
        if (this.video.paused) {
            this.video.play();
        } else {
            this.video.pause();
        }
    }
    
    toggleMute() {
        this.video.muted = !this.video.muted;
        this.volumeBtn.textContent = this.video.muted ? '✕' : '♪';
    }
    
    toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            this.container.requestFullscreen();
        }
    }
    
    updateProgress() {
        const progress = (this.video.currentTime / this.video.duration) * 100;
        this.progressFill.style.width = progress + '%';
        this.currentTimeSpan.textContent = this.formatTime(this.video.currentTime);
    }
    
    updateDuration() {
        this.durationSpan.textContent = this.formatTime(this.video.duration);
    }
    
    seek(e) {
        const rect = this.progressBar.getBoundingClientRect();
        const pos = (e.clientX - rect.left) / rect.width;
        this.video.currentTime = pos * this.video.duration;
    }
    
    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
}