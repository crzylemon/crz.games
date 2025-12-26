
class CRE {
    constructor(canvas, game) {
        this.crzgames = false; //running from crz.games (then use uploads/games/...) (crz.games is the url, not like crz.games.com or crzgames.com, just crz.games)
        this.base = "";
        // change this to true when deploying to crz.games
        if (window.location.hostname === "crz.games" || window.location.hostname.includes("ngrok")) {
            this.crzgames = true;
            this.base = "uploads/games/";
        }
        this.version = "mega-2-1"
        this.canvas = canvas;
        this.container = document.getElementById("gamecontainer");
        
        // Rendering setup
        this.gl = canvas.getContext("webgl", { antialias: false, alpha: false }) || 
                   canvas.getContext("experimental-webgl", { antialias: false, alpha: false });
        
        if (!this.gl) {
            throw new Error("WebGL not supported - GPU acceleration required");
        }
        this.initWebGL();
        
        // Set dimensions first
        this._width = 1920;
        this._height = 1080;
        
        // Create overlay canvas for GUI
        this.guiCanvas = document.createElement('canvas');
        this.guiCanvas.width = this._width;
        this.guiCanvas.height = this._height;
        this.guiCanvas.style.position = 'absolute';
        this.guiCanvas.style.top = '0';
        this.guiCanvas.style.left = '0';
        this.guiCanvas.style.width = '100%';
        this.guiCanvas.style.height = '100%';

        this.guiCanvas.style.zIndex = '5';
        this.guiCanvas.style.pointerEvents = 'none';
        this.guiCanvas.style.background = 'transparent';
        this.container.appendChild(this.guiCanvas);
        this.ctx = this.guiCanvas.getContext('2d');
        
        this.keys = {};
        this.mouseDelta = { x: 0, y: 0 };
        this.mouseCapture = false;
        this.lastMousePos = { x: 0, y: 0 };
        
        // Mouse delta tracking
        this.entities = [];
        this.EntityClasses = {};
        this.RUN_TYPE = "CLIENT"; // Can be CLIENT, SERVER, SHARED
        this.uiElements = [];
        this.loading = false;
        this.loadingScreen = {
            background: '#000',
            text: 'Loading...',
            textColor: '#fff',
            font: '24px Arial',
            progress: 0,
            showProgress: true
        };
        this.preloadQueue = [];
        this.consoleOpen = false;
        this.consoleHistory = [];
        this.Transform = Transform;
        this.conCommands = {};
        this.conVars = {};
        this.keyBinds = {};
        this.game = game;
        this.debug = false;
        
        // Chat system
        this.chat = {
            open: false,
            messages: [],
            maxMessages: 50,
            inputElement: null
        };
        
        // Triangle intersection system
        this.intersectionGrid = new Map();
        this.gridSize = 200; // Larger grid for better performance
        
        // Physics system
        this.physics = {
            gravity: new Vector(0, -9.81, 0),
            bodies: [],
            colliders: []
        };
        
        // Shadow mapping
        this.shadows = {
            enabled: false,
            mapSize: 1024,
            framebuffer: null,
            texture: null,
            lightMatrix: null
        };
        
        // Multiplayer networking
        this.network = {
            socket: null,
            connected: false,
            players: new Map(),
            isHost: false
        };
        
        // Scripting system
        this.scripts = {
            nodes: new Map(),
            connections: [],
            variables: new Map(),
            functions: new Map()
        };
        // Load binds from localStorage
        this.loadBinds();
        console.log("CRE initialized for game:", game);
        console.log("this.game: ", this.game);
        // add convar for cheats and stuff
        this.cheats = this.RegisterConVar("sv_cheats", "0", FCVAR_PROTECTED, "Enable cheat commands");
        this.fov = this.RegisterConVar("fov", "60", FCVAR_NONE, "Camera field of view");
        this.nearplane = this.RegisterConVar("near_plane", "0.01", FCVAR_NONE, "Near clipping plane");
        this.farplane = this.RegisterConVar("far_plane", "1000", FCVAR_NONE, "Far clipping plane");
        
        // Host-only convars
        this.hostname = this.RegisterConVar("hostname", "CRENGINE Server", FCVAR_PROTECTED, "Server hostname");
        this.maxplayers = this.RegisterConVar("sv_maxplayers", "8", FCVAR_PROTECTED, "Maximum number of players");
        this.password = this.RegisterConVar("sv_password", "", FCVAR_PROTECTED, "Server password");
        
        // Color correction
        this.brightness = this.RegisterConVar("r_brightness", "1.0", FCVAR_NONE, "Brightness adjustment");
        this.contrast = this.RegisterConVar("r_contrast", "1.1", FCVAR_NONE, "Contrast adjustment");
        this.saturation = this.RegisterConVar("r_saturation", "1.2", FCVAR_NONE, "Saturation adjustment");
        this.gamma = this.RegisterConVar("r_gamma", "2.2", FCVAR_NONE, "Gamma correction");
        
        // Register built-in commands
        this.RegisterConCommand("ent_spawn", (...args) => this.SpawnEntity(args[0], ...args.slice(1)), FCVAR_CHEAT, "Spawn an entity");
        this.RegisterConCommand("game_reset", () => this.UTIL_RESET(), FCVAR_CHEAT, "Reset the game");
        this.RegisterConCommand("ent_remove", (name) => {
            const entity = this.entities.find(e => e.name === name);
            if (entity) this.UTIL_REMOVE(entity);
        }, FCVAR_CHEAT, "Remove an entity");
        this.RegisterConCommand("clear", () => document.getElementById('console-output').textContent = '', FCVAR_NONE, "Clear console output");
        this.RegisterConCommand("map_load", (mapName) => {
            fetch(`${this.base}${this.game}/maps/${mapName}.map?v=${Date.now()}`)
                .then(r => r.json())
                .then(data => this.LoadMap(data))
                .catch(() => this.addToConsole(`Failed to load map: ${mapName}`));
        }, FCVAR_NONE, "Load a map");
        this.RegisterConCommand("model_load", (modelName) => {
            this.LoadModel(modelName).then(model => {
                if (model) {
                    window.loadedModel = model;
                    this.addToConsole(`Model '${modelName}' loaded and available as window.loadedModel`);
                }
            });
        }, FCVAR_NONE, "Load a model");
        this.RegisterConCommand("width", (value) => {
            if (value) this.width = parseInt(value);
            this.addToConsole(`Width = ${this.width}`);
        }, FCVAR_NONE, "Set canvas width");
        this.RegisterConCommand("height", (value) => {
            if (value) this.height = parseInt(value);
            this.addToConsole(`Height = ${this.height}`);
        }, FCVAR_NONE, "Set canvas height");
        this.RegisterConCommand("bind", (key, ...commandParts) => {
            if (commandParts.length > 0) {
                const keyLower = key.toLowerCase();
                const command = commandParts.join(' ').replace(/"/g, '');
                this.keyBinds[keyLower] = command;
                this.saveBinds();
                this.addToConsole(`Bound ${keyLower} to "${command}"`);
            } else {
                this.addToConsole("Usage: bind <key> <command>");
            }
        }, FCVAR_NONE, "Bind a key to a command");
        this.RegisterConCommand("unbind", (key) => {
            if (key) {
                const keyLower = key.toLowerCase();
                if (this.keyBinds[keyLower]) {
                    delete this.keyBinds[keyLower];
                    this.saveBinds();
                    this.addToConsole(`Unbound ${keyLower}`);
                } else {
                    this.addToConsole(`${keyLower} is not bound`);
                }
            } else {
                this.addToConsole("Usage: unbind <key>");
            }
        }, FCVAR_NONE, "Unbind a key");
        this.RegisterConCommand("bindlist", () => {
            if (Object.keys(this.keyBinds).length === 0) {
                this.addToConsole("No key binds");
            } else {
                this.addToConsole("Current key binds:");
                Object.entries(this.keyBinds).forEach(([key, cmd]) => {
                    this.addToConsole(`  ${key} = "${cmd}"`);
                });
            }
        }, FCVAR_NONE, "List all key binds");
        this.RegisterConCommand("unbindall", () => {
            this.keyBinds = {};
            this.saveBinds();
            this.addToConsole("All key binds cleared");
        }, FCVAR_NONE, "Clear all key binds");
        this.RegisterConCommand("toggledebug", () => this.debug = !this.debug, FCVAR_NONE, "Toggle debug display");
        this.RegisterConCommand("toggleconsole", () => this.toggleConsole(), FCVAR_NONE, "Toggle console visibility");
        this.RegisterConCommand("echo", (echo) => this.addToConsole(echo), FCVAR_NONE, "Echo to console");
        this.RegisterConCommand("anim_play", (entityName, animName) => {
            const entity = this.entities.find(e => e.name === entityName);
            if (entity) this.playAnimation(entity, animName);
            else this.addToConsole(`Entity '${entityName}' not found`);
        }, FCVAR_CHEAT, "Play animation on entity");
        this.RegisterConCommand("anim_stop", (entityName) => {
            const entity = this.entities.find(e => e.name === entityName);
            if (entity) this.stopAnimation(entity);
            else this.addToConsole(`Entity '${entityName}' not found`);
        }, FCVAR_CHEAT, "Stop animation on entity");
        this.RegisterConCommand("model_anim_play", (entityName, animName) => {
            const entity = this.entities.find(e => e.name === entityName);
            if (entity) this.playModelAnimation(entity, animName);
            else this.addToConsole(`Entity '${entityName}' not found`);
        }, FCVAR_CHEAT, "Play model animation on entity");
        this.RegisterConCommand("model_anim_stop", (entityName) => {
            const entity = this.entities.find(e => e.name === entityName);
            if (entity) this.stopModelAnimation(entity);
            else this.addToConsole(`Entity '${entityName}' not found`);
        }, FCVAR_CHEAT, "Stop model animation on entity");
        this.RegisterConCommand("physics_add", (entityName) => {
            const entity = this.entities.find(e => e.name === entityName);
            if (entity) this.addPhysicsBody(entity);
            else this.addToConsole(`Entity '${entityName}' not found`);
        }, FCVAR_CHEAT, "Add physics to entity");
        this.RegisterConCommand("physics_impulse", (entityName, x, y, z) => {
            const entity = this.entities.find(e => e.name === entityName);
            if (entity && entity.CRE.physics) {
                entity.CRE.physics.velocity = entity.CRE.physics.velocity.add(new Vector(+x, +y, +z));
            }
        }, FCVAR_CHEAT, "Apply impulse to physics entity");
        this.RegisterConCommand("shadows", (enabled) => {
            this.shadows.enabled = enabled === "1";
            if (this.shadows.enabled && !this.shadows.framebuffer) {
                this.initShadowMapping();
            }
            this.addToConsole(`Shadows: ${this.shadows.enabled ? 'ON' : 'OFF'}`);
        }, FCVAR_NONE, "Toggle shadow mapping");
        this.RegisterConCommand("host", (port = 27015) => {
            this.startLANServer();
        }, FCVAR_NONE, "Host LAN server");
        this.RegisterConCommand("connect", (ip, port = 27015) => {
            if (ip) this.connectToLAN(ip, parseInt(port));
            else this.addToConsole("Usage: connect <ip> [port]");
        }, FCVAR_NONE, "Connect to LAN server");
        this.RegisterConCommand("disconnect", () => {
            this.disconnectFromServer();
        }, FCVAR_NONE, "Disconnect from server");
        this.RegisterConCommand("players", () => {
            if (this.network.connected) {
                this.addToConsole(`Connected players: ${this.network.players.size + 1}`);
                const hostDisplay = this.network.user?.display || this.network.playerId || 'Self';
                this.addToConsole(`Host: ${this.network.playerId} (${hostDisplay}) (You)`);
                for (const playerId of this.network.players.keys()) {
                    this.addToConsole(`Player: ${playerId}`);
                }
            } else {
                this.addToConsole('Not connected to a server');
                this.addToConsole('Local player: Self (You)');
            }
        }, FCVAR_PROTECTED, "List connected players");
        this.RegisterConCommand("kick", (playerId) => {
            if (!playerId) {
                this.addToConsole('Usage: kick <player_id>');
                return;
            }
            // TODO: Implement kick functionality
            this.addToConsole(`Kicked player: ${playerId}`);
        }, FCVAR_PROTECTED, "Kick a player from the server");
        this.RegisterConCommand("script_run", (code) => {
            this.executeScript(code);
        }, FCVAR_CHEAT, "Execute JavaScript code");
        this.RegisterConCommand("script_var", (name, value) => {
            this.scripts.variables.set(name, value);
            this.addToConsole(`Set ${name} = ${value}`);
        }, FCVAR_CHEAT, "Set script variable");
        this.RegisterConCommand("script_list", () => {
            this.addToConsole('Script Variables:');
            for (const [key, value] of this.scripts.variables) {
                this.addToConsole(`  ${key} = ${value}`);
            }
        }, FCVAR_NONE, "List script variables");
        this.RegisterConCommand("say", () => {
            if (this.chat) {
                this.openChat();
            }
        }, FCVAR_NONE, "Open chat to send a message");
        this.RegisterConCommand("light_list", () => {
            const lights = this.entities.filter(ent => 
                ent.constructor.name === 'Light' || 
                ent.constructor.name === 'LightSpot' || 
                ent.constructor.name === 'LightSun'
            );
            this.addToConsole(`Found ${lights.length} lights:`);
            lights.forEach((light, i) => {
                const pos = light.CRE.transform.position;
                this.addToConsole(`  ${i}: ${light.constructor.name} at (${pos.x.toFixed(1)}, ${pos.y.toFixed(1)}, ${pos.z.toFixed(1)}) intensity=${light.CRE.intensity}`);
            });
        }, FCVAR_NONE, "List all lights in scene");

        this.RegisterConCommand("help", (command) => {
            if (command) {
                // Check if it's a command
                if (this.conCommands[command]) {
                    const cmd = this.conCommands[command];
                    this.addToConsole(`${command}:`);
                    this.addToConsole(cmd.help);
                    return;
                }
                // Check if it's a convar
                if (this.conVars[command]) {
                    const cvar = this.conVars[command];
                    this.addToConsole(`${command} = "${cvar.value}"`);
                    this.addToConsole(`Default: ${cvar.defaultValue}`);
                    this.addToConsole(cvar.help || 'No description available');
                    return;
                }
                this.addToConsole(`Unknown command or variable: ${command}`);
            } else {
                this.addToConsole("Available commands:");
                Object.keys(this.conCommands).forEach(cmd => {
                    this.addToConsole(`  ${cmd} - ${this.conCommands[cmd].help}`);
                });
                this.addToConsole("Available variables:");
                Object.keys(this.conVars).forEach(cvar => {
                    this.addToConsole(`  ${cvar} - ${this.conVars[cvar].help}`);
                });
            }
        }, FCVAR_NONE, "Get help with a command or list all commands");
        
        // Initialize chat system
        this.initChat();
        // 3D Camera
        this.camera = {
            position: new Vector(0, 0, -10),
            rotation: new Vector(0, 0, 0),
            fov: 60,
            near: 0.01,
            far: 1000
        };

        this.models = {};
        this.textures = new Map();

        // Setup input
        window.addEventListener("keydown", e => {
            if (!this.consoleOpen && !this.chat.open) {
                this.keys[e.key] = true;
            }
            
            // Chat key (y)
            if (e.key === 'y' && !this.consoleOpen && !this.chat.open) {
                e.preventDefault();
                this.openChat();
                return;
            }
            
            // Check for key binds (only when console and chat are closed or for special keys)
            if (this.keyBinds[e.key.toLowerCase()] && ((!this.consoleOpen && !this.chat.open) || e.key === '`')) {
                e.preventDefault();
                this.ConsoleHandler(...this.keyBinds[e.key.toLowerCase()].split(' '));
            }
        });
        window.addEventListener("keyup", e => {
            if (!this.consoleOpen && !this.chat.open) this.keys[e.key] = false;
        });
        
        // Mouse capture and movement
        this.canvas.addEventListener('click', () => {
            if (!this.mouseCapture) {
                this.canvas.requestPointerLock();
            }
        });
        
        document.addEventListener('pointerlockchange', () => {
            this.mouseCapture = document.pointerLockElement === this.canvas;
            if (!this.mouseCapture) {
                this.mouseDelta.x = 0;
                this.mouseDelta.y = 0;
            }
        });
        document.addEventListener('mousemove', e => {
            this.lastMousePos.x = e.clientX;
            this.lastMousePos.y = e.clientY;
            if (this.mouseCapture && !this.consoleOpen && !this.chat.open) {
                this.mouseDelta.x = e.movementX || 0;
                this.mouseDelta.y = e.movementY || 0;
            }
        });
        
        // UI click handling
        this.guiCanvas.style.pointerEvents = 'none';
        this.guiCanvas.addEventListener('click', e => {
            const rect = this.guiCanvas.getBoundingClientRect();
            const x = (e.clientX - rect.left) * (this.guiCanvas.width / rect.width);
            const y = (e.clientY - rect.top) * (this.guiCanvas.height / rect.height);
            
            this.uiElements.forEach(element => {
                if (element.type === 'button' && element.visible && element.onClick) {
                    if (x >= element.x && x <= element.x + element.width &&
                        y >= element.y && y <= element.y + element.height) {
                        element.onClick();
                    }
                }
            });
        });
        
        // Enable pointer events only when UI elements exist
        this.updateGUIPointerEvents = () => {
            if (!this.lastMousePos.x && !this.lastMousePos.y) return;
            
            const rect = this.guiCanvas.getBoundingClientRect();
            const mouseX = (this.lastMousePos.x - rect.left) * (this.guiCanvas.width / rect.width);
            const mouseY = (this.lastMousePos.y - rect.top) * (this.guiCanvas.height / rect.height);
            
            // Only check if mouse coordinates are valid
            if (mouseX < 0 || mouseY < 0 || mouseX > this.guiCanvas.width || mouseY > this.guiCanvas.height) {
                this.guiCanvas.style.pointerEvents = 'none';
                return;
            }
            
            const overButton = this.uiElements.some(el => 
                el.type === 'button' && el.visible &&
                mouseX >= el.x && mouseX <= el.x + el.width &&
                mouseY >= el.y && mouseY <= el.y + el.height
            );
            
            this.guiCanvas.style.pointerEvents = overButton ? 'auto' : 'none';
        };

        // Set fixed canvas size
        this.canvas.width = this._width;
        this.canvas.height = this._height;
        this.container.style.width = this._width + 'px';
        this.container.style.height = this._height + 'px';
        
        // Property setters for width/height
        Object.defineProperty(this, 'width', {
            get: () => this._width,
            set: (value) => {
                this._width = value;
                this.canvas.width = value;
                this.guiCanvas.width = value;
                this.container.style.width = value + 'px';
            }
        });
        
        Object.defineProperty(this, 'height', {
            get: () => this._height,
            set: (value) => {
                this._height = value;
                this.canvas.height = value;
                this.guiCanvas.height = value;
                this.container.style.height = value + 'px';
            }
        });

        // Enums for registration
        this.Enums = {
            RunType: {
                CLIENT: "CLIENT",
                SERVER: "SERVER",
                SHARED: "SHARED"
            },
            ConVarFlags: {
                NONE: FCVAR_NONE,
                CHEAT: FCVAR_CHEAT,
                REPLICATED: FCVAR_REPLICATED,
                ARCHIVE: FCVAR_ARCHIVE,
                NOTIFY: FCVAR_NOTIFY,
                USERINFO: FCVAR_USERINFO,
                PROTECTED: FCVAR_PROTECTED,
                SPONLY: FCVAR_SPONLY,
                DEMO: FCVAR_DEMO,
                DONTRECORD: FCVAR_DONTRECORD
            },
            DrawMode: {
                CUBE: 0,
                MODEL: 1,
                SPHERE: 2,
            }
        };

        // Register built-in light entities
        this.RegisterEntity(Light, "light");
        this.RegisterEntity(LightSpot, "light_spot");
        this.RegisterEntity(LightSun, "light_sun");
        this.RegisterEntity(PhysicsBox, "physics_box");

        // Start game loop
        this.lastTime = performance.now();
        this.gameLoop = this.gameLoop.bind(this);
        requestAnimationFrame(this.gameLoop);
    }
    
    initWebGL() {
        const gl = this.gl;
        
        gl.viewport(0, 0, this.canvas.width, this.canvas.height);
        
        // Vertex shader with texture support
        const vsSource = `
            attribute vec4 aVertexPosition;
            attribute vec3 aVertexColor;
            attribute vec2 aTextureCoord;
            uniform mat4 uModelViewMatrix;
            uniform mat4 uProjectionMatrix;
            varying lowp vec3 vColor;
            varying highp vec2 vTextureCoord;
            void main(void) {
                gl_Position = uProjectionMatrix * uModelViewMatrix * aVertexPosition;
                vColor = aVertexColor;
                vTextureCoord = aTextureCoord;
            }
        `;
        
        // Fragment shader with texture support
        const fsSource = `
            precision mediump float;
            varying lowp vec3 vColor;
            varying highp vec2 vTextureCoord;
            uniform sampler2D uSampler;
            uniform bool uUseTexture;
            uniform vec3 uBaseColor;
            void main(void) {
                if (uUseTexture) {
                    vec4 texColor = texture2D(uSampler, vTextureCoord);
                    vec3 shadedBaseColor = uBaseColor * vColor;
                    vec3 shadedTexColor = texColor.rgb * vColor;
                    vec3 finalColor = mix(shadedBaseColor, shadedTexColor, texColor.a);
                    gl_FragColor = vec4(finalColor, 1.0);
                } else {
                    gl_FragColor = vec4(vColor, 1.0);
                }
            }
        `;
        
        this.shaderProgram = this.createShaderProgram(gl, vsSource, fsSource);
        
        this.programInfo = {
            program: this.shaderProgram,
            attribLocations: {
                vertexPosition: gl.getAttribLocation(this.shaderProgram, 'aVertexPosition'),
                vertexColor: gl.getAttribLocation(this.shaderProgram, 'aVertexColor'),
                textureCoord: gl.getAttribLocation(this.shaderProgram, 'aTextureCoord'),
            },
            uniformLocations: {
                projectionMatrix: gl.getUniformLocation(this.shaderProgram, 'uProjectionMatrix'),
                modelViewMatrix: gl.getUniformLocation(this.shaderProgram, 'uModelViewMatrix'),
                uSampler: gl.getUniformLocation(this.shaderProgram, 'uSampler'),
                uUseTexture: gl.getUniformLocation(this.shaderProgram, 'uUseTexture'),
                uBaseColor: gl.getUniformLocation(this.shaderProgram, 'uBaseColor'),
            },
        };
        
        gl.enable(gl.DEPTH_TEST);
        gl.depthFunc(gl.LEQUAL);
        gl.enable(gl.CULL_FACE);
        gl.cullFace(gl.BACK);
        gl.frontFace(gl.CCW);
        gl.enable(gl.BLEND);
        gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
        
        // Initialize shadow mapping
        this.initShadowMapping();
    }
    
    createShaderProgram(gl, vsSource, fsSource) {
        const vertexShader = this.loadShader(gl, gl.VERTEX_SHADER, vsSource);
        const fragmentShader = this.loadShader(gl, gl.FRAGMENT_SHADER, fsSource);
        
        const shaderProgram = gl.createProgram();
        gl.attachShader(shaderProgram, vertexShader);
        gl.attachShader(shaderProgram, fragmentShader);
        gl.linkProgram(shaderProgram);
        
        if (!gl.getProgramParameter(shaderProgram, gl.LINK_STATUS)) {
            console.error('Unable to initialize shader program: ' + gl.getProgramInfoLog(shaderProgram));
            return null;
        }
        
        return shaderProgram;
    }
    
    loadShader(gl, type, source) {
        const shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            console.error('An error occurred compiling the shaders: ' + gl.getShaderInfoLog(shader));
            gl.deleteShader(shader);
            return null;
        }
        
        return shader;
    }


    
    // Register a class as an entity
    RegisterEntity(cls, name, runType = this.Enums.RunType.CLIENT) {
        this.EntityClasses[name] = { cls, runType };
    }

    ConsoleHandler(command, ...args) {
        this.addToConsole(`] ${command} ${args.join(' ')}`);
        
        // Check registered commands first
        if (this.conCommands[command]) {
            const concommand = this.conCommands[command];
            // Check if it's a cheat command and cheats are disabled
            if (concommand.hasFlag(FCVAR_CHEAT) && this.GetConVar("sv_cheats") !== "1") {
                this.addToConsole(`Can't execute ${command}, cheats are not enabled.`);
                return;
            }
            // Check if it's a protected command and user is not host
            if (concommand.hasFlag(FCVAR_PROTECTED) && this.network.connected && !this.network.isHost) {
                this.addToConsole(`Can't execute ${command}, only server host can use this.`);
                return;
            }
            concommand.execute(...args);
            return;
        }
        
        // Check convars
        if (this.conVars[command]) {
            if (args.length > 0) {
                const convar = this.conVars[command];
                // Check if it's a cheat convar and cheats are disabled
                if (convar.hasFlag(FCVAR_CHEAT) && this.GetConVar("sv_cheats") !== "1") {
                    this.addToConsole(`Can't change ${command}, cheats are not enabled.`);
                    return;
                }
                // Check if it's a protected convar and user is not host
                if (convar.hasFlag(FCVAR_PROTECTED) && this.network.connected && !this.network.isHost) {
                    this.addToConsole(`Can't change ${command}, only server host can use this.`);
                    return;
                }
                convar.value = args[0];
                this.addToConsole(`${command} = ${args[0]}`);
            } else {
                this.addToConsole(`${command} = ${this.conVars[command].value}`);
            }
            return;
        }
        
        // Unknown command
        this.addToConsole(`Unknown command: ${command}`);
    }

    addToConsole(text) {
        const output = document.getElementById('console-output');
        output.textContent += text + '\n';
        output.scrollTop = output.scrollHeight;
    }

    toggleConsole() {
        this.consoleOpen = !this.consoleOpen;
        const console = document.getElementById('console');
        const input = document.getElementById('console-input');
        const suggestions = document.getElementById('console-suggestions');
        
        if (this.consoleOpen) {
            console.style.display = 'flex';
            input.focus();
        } else {
            console.style.display = 'none';
            input.blur();
            suggestions.style.display = 'none';
        }
    }

    // Add an instance of a registered entity
    SpawnEntity(name, ...args) {
        if (!this.EntityClasses[name]) {
            console.warn(`Entity ${name} not registered!`);
            return null;
        }
        const { cls, runType } = this.EntityClasses[name];
        // Only spawn if run type matches
        if (runType === this.RUN_TYPE || runType === this.Enums.RunType.SHARED) {
            const instance = new cls(...args);
            
            // Auto-bind methods to preserve 'this' context
            Object.getOwnPropertyNames(Object.getPrototypeOf(instance))
                .filter(name => name !== 'constructor' && typeof instance[name] === 'function')
                .forEach(methodName => {
                    instance[methodName] = instance[methodName].bind(instance);
                });
            
            if (instance.init && (!instance.CRE || !instance.CRE.multiplayer || instance.CRE.isClient === true)) {
                instance.init();
            }
            
            // Auto-add physics if usePhysics is true
            if (instance.CRE && instance.CRE.usePhysics) {
                this.addPhysicsBody(instance, 
                    instance.CRE.physicsMass || 1,
                    instance.CRE.physicsBounce || 0.3,
                    instance.CRE.physicsFriction || 0.7,
                    instance.CRE.physicsCharacter || false
                );
            }
            
            this.entities.push(instance);
            return instance;
        }
        return null;
    }

    // Chat system methods
    initChat() {
        // Create chat UI elements
        this.addUIText('chat-messages', '', 10, this._height - 200, { color: 'white', font: '14px Arial' });
        
        // Create chat input element
        this.chat.inputElement = document.createElement('input');
        this.chat.inputElement.type = 'text';
        this.chat.inputElement.id = 'chat-input';
        this.chat.inputElement.style.cssText = `
            position: absolute;
            bottom: 10px;
            left: 10px;
            width: 300px;
            padding: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: 1px solid #555;
            font-family: Arial;
            font-size: 14px;
            display: none;
            z-index: 1000;
        `;
        
        this.chat.inputElement.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const message = this.chat.inputElement.value.trim();
                if (message) {
                    this.sendChatMessage(message);
                    this.chat.inputElement.value = '';
                }
                this.closeChat();
            } else if (e.key === 'Escape') {
                this.chat.inputElement.value = '';
                this.closeChat();
            }
        });
        
        if (this.container) {
            this.container.appendChild(this.chat.inputElement);
        }
    }
    
    openChat() {
        this.chat.open = true;
        this.chat.inputElement.style.display = 'block';
        this.chat.inputElement.focus();
    }
    
    closeChat() {
        this.chat.open = false;
        this.chat.inputElement.style.display = 'none';
        this.chat.inputElement.blur();
    }
    
    sendChatMessage(message) {
        const displayName = this.network.user?.display || this.network.playerId || 'Self';
        
        if (this.network.connected) {
            this.sendMessage({
                type: 'chat_message',
                message: message,
                displayName: displayName
            });
        }
        
        // Always show locally for sender
        this.addChatMessage(`${displayName} : ${message}`);
    }
    
    addChatMessage(message) {
        this.chat.messages.push({ message });
        
        // Keep only last maxMessages
        if (this.chat.messages.length > this.chat.maxMessages) {
            this.chat.messages.shift();
        }
        
        this.updateChatDisplay();
    }
    
    updateChatDisplay() {
        const chatElement = this.getUIElement('chat-messages');
        if (chatElement) {
            const displayMessages = this.chat.messages.slice(-5); // Show last 5 messages
            chatElement.text = displayMessages.map(msg => 
                msg.message
            ).join('\n');
        }
    }
    
    // Check if a key is pressed
    isKeyPressed(key) {
        return !!this.keys[key];
    }
    
    // Register console command
    RegisterConCommand(name, func, flags, help) {
        this.conCommands[name] = new ConCommand(name, func, flags, help);
        return this.conCommands[name];
    }
    
    // Register console variable
    RegisterConVar(name, defaultValue, flags, help) {
        this.conVars[name] = new ConVar(name, defaultValue, flags, help);
        return this.conVars[name];
    }
    
    // Get convar value
    GetConVar(name) {
        return this.conVars[name] ? this.conVars[name].value : null;
    }
    
    // Load map from JSON
    LoadMap(mapData) {
        // Don't call UTIL_RESET here - it causes infinite loops
        this.brushes = [];
        
        // Load entities
        mapData.entities?.forEach(ent => {
            try {
                const instance = this.SpawnEntity(ent.type, ...ent.args || []);
                if (instance && instance.CRE && instance.CRE.transform) {
                    if (ent.position) {
                        instance.CRE.transform.setPosition(...ent.position);
                    }
                    if (ent.scale) {
                        instance.CRE.transform.setScale(...ent.scale);
                    }
                } else if (!instance) {
                    this.addToConsole(`Warning: Failed to spawn entity '${ent.type}'`);
                }
            } catch (e) {
                this.addToConsole(`Error spawning entity '${ent.type}': ${e.message}`);
            }
        });
        
        // Load brushes with scale factor
        const mapScale = 2; // Scale maps up to match model units
        mapData.brushes?.forEach(brush => {
            this.brushes.push({
                vertices: brush.vertices.map(v => new Vector(v[0] * mapScale, v[1] * mapScale, v[2] * mapScale)),
                texture: brush.texture || "#f0f",
                faces: brush.faces || []
            });
        });
        
        this.addToConsole(`Map loaded: ${mapData.name || 'Unnamed'}`);
    }
    
    // Load model from JSON
    LoadModel(name) {
        return fetch(`${this.base}${this.game}/models/${name}.json?v=${Date.now()}`)
            .then(r => r.json())
            .then(data => {
                const model = {
                    objects: data.objects.map(obj => ({
                        name: obj.name,
                        vertices: obj.vertices.map(v => new Vector(v[0], v[1], v[2])),
                        faces: obj.faces,
                        normals: obj.normals?.map(n => new Vector(n[0], n[1], n[2])) || [],
                        uvs: obj.uvs || [],
                        material: obj.material,
                        color: obj.color,
                        texture: obj.texture,
                        parent: obj.parent || null,
                        transform: obj.transform || { position: [0,0,0], rotation: [0,0,0], scale: [1,1,1] }
                    })),
                    bones: data.bones || {},
                    animations: data.animations || {}
                };
                if (this.debug) {
                    this.addToConsole(`Model loaded: ${name}`);
                }
                this.models[name] = model;
                return model;
            })
            .catch(err => {
                this.addToConsole(`Failed to load model: ${name}`);
                return null;
            });
    }
    
    // Render model with hierarchy support
    drawModel(model, transform, fallbackColor = "#888", animationState = null) {
        if (!model || !model.objects) return;
        
        // Calculate bone transforms if animation is playing
        const boneTransforms = {};
        if (animationState && model.animations[animationState.name]) {
            this.calculateBoneTransforms(model, animationState, boneTransforms);
        }
        
        model.objects.forEach(obj => {
            const objColor = obj.color || fallbackColor;
            const objTexture = obj.texture ? `${this.base}${this.game}/materials/${obj.texture}` : null;
            
            // Calculate object transform (hierarchy + animation)
            const objTransform = this.calculateObjectTransform(obj, transform, boneTransforms);
            
            obj.faces.forEach(face => {
                if (face.length >= 3) {
                    // Calculate face normal and lighting once per face
                    const v1Idx = face[0][0] - 1;
                    const v2Idx = face[1][0] - 1;
                    const v3Idx = face[2][0] - 1;
                    
                    if (v1Idx >= 0 && v2Idx >= 0 && v3Idx >= 0 && 
                        v1Idx < obj.vertices.length && v2Idx < obj.vertices.length && v3Idx < obj.vertices.length) {
                        
                        const fv1 = this.transformVertex(obj.vertices[v1Idx], objTransform);
                        const fv2 = this.transformVertex(obj.vertices[v2Idx], objTransform);
                        const fv3 = this.transformVertex(obj.vertices[v3Idx], objTransform);
                        
                        const ab = fv2.subtract(fv1);
                        const ac = fv3.subtract(fv1);
                        const faceNormal = ab.cross(ac).normalize();
                        const faceCenter = new Vector(
                            (fv1.x + fv2.x + fv3.x) / 3,
                            (fv1.y + fv2.y + fv3.y) / 3,
                            (fv1.z + fv2.z + fv3.z) / 3
                        );
                        let faceShade = this.calculateLighting(faceCenter, faceNormal);
                    
                        // Fan triangulation for any polygon
                        for (let i = 1; i < face.length - 1; i++) {
                            const v1Idx = face[0][0] - 1;
                            const v2Idx = face[i][0] - 1;
                            const v3Idx = face[i + 1][0] - 1;
                            
                            if (v1Idx >= 0 && v2Idx >= 0 && v3Idx >= 0 && 
                                v1Idx < obj.vertices.length && v2Idx < obj.vertices.length && v3Idx < obj.vertices.length) {
                                
                                const v1 = this.transformVertex(obj.vertices[v1Idx], objTransform);
                                const v2 = this.transformVertex(obj.vertices[v2Idx], objTransform);
                                const v3 = this.transformVertex(obj.vertices[v3Idx], objTransform);
                                
                                let shade = faceShade;
                            
                            // Apply material effects
                            if (obj.material === 'emissive') {
                                shade = 1.0;
                            } else if (obj.material === 'metallic') {
                                shade = Math.max(0.5, shade * 1.2);
                            }
                            
                            // Get UVs if available
                            let uvs = null;
                            if (obj.uvs && face[0][1] && face[i][1] && face[i + 1][1]) {
                                const uv1Idx = face[0][1] - 1;
                                const uv2Idx = face[i][1] - 1;
                                const uv3Idx = face[i + 1][1] - 1;
                                if (uv1Idx >= 0 && uv2Idx >= 0 && uv3Idx >= 0 && 
                                    uv1Idx < obj.uvs.length && uv2Idx < obj.uvs.length && uv3Idx < obj.uvs.length) {
                                    uvs = [obj.uvs[uv1Idx], obj.uvs[uv2Idx], obj.uvs[uv3Idx]];
                                }
                            }
                            
                            this.renderQueue.push({
                                type: 'triangle',
                                verts: [v1, v2, v3],
                                color: objColor,
                                texture: objTexture,
                                shade,
                                uvs
                            });
                            }
                        }
                    }
                }
            });
        });
    }
    
    calculateObjectTransform(obj, baseTransform, boneTransforms) {
        let objTransform = {
            position: new Vector(...obj.transform.position),
            rotation: new Vector(...obj.transform.rotation),
            scale: new Vector(...obj.transform.scale)
        };
        
        // Apply bone transform if object is bound to a bone
        if (obj.parent && boneTransforms[obj.parent]) {
            const boneT = boneTransforms[obj.parent];
            objTransform.position = objTransform.position.add(boneT.position);
            objTransform.rotation = objTransform.rotation.add(boneT.rotation);
        }
        
        // Combine with base transform
        return {
            position: baseTransform.position.add(objTransform.position),
            rotation: baseTransform.rotation.add(objTransform.rotation),
            scale: new Vector(
                baseTransform.scale.x * objTransform.scale.x,
                baseTransform.scale.y * objTransform.scale.y,
                baseTransform.scale.z * objTransform.scale.z
            )
        };
    }
    
    calculateBoneTransforms(model, animState, boneTransforms) {
        const anim = model.animations[animState.name];
        if (!anim) return;
        
        Object.keys(anim.bones).forEach(boneName => {
            const boneAnim = anim.bones[boneName];
            const t = animState.time;
            
            // Find keyframes
            let k1 = boneAnim.keyframes[0];
            let k2 = boneAnim.keyframes[boneAnim.keyframes.length - 1];
            
            for (let i = 0; i < boneAnim.keyframes.length - 1; i++) {
                if (t >= boneAnim.keyframes[i].time && t <= boneAnim.keyframes[i + 1].time) {
                    k1 = boneAnim.keyframes[i];
                    k2 = boneAnim.keyframes[i + 1];
                    break;
                }
            }
            
            // Interpolate
            const alpha = k2.time === k1.time ? 0 : (t - k1.time) / (k2.time - k1.time);
            
            boneTransforms[boneName] = {
                position: this.lerpVector(
                    new Vector(...(k1.position || [0,0,0])),
                    new Vector(...(k2.position || [0,0,0])),
                    alpha
                ),
                rotation: this.lerpVector(
                    new Vector(...(k1.rotation || [0,0,0])),
                    new Vector(...(k2.rotation || [0,0,0])),
                    alpha
                )
            };
        });
    }
    
    // Transform vertex by transform matrix
    transformVertex(vertex, transform) {
        // Apply scale first
        let v = new Vector(
            vertex.x * transform.scale.x,
            vertex.y * transform.scale.y,
            vertex.z * transform.scale.z
        );
        
        // Apply rotation
        const yaw = transform.rotation.y * Math.PI / 180;
        const pitch = transform.rotation.x * Math.PI / 180;
        
        // Rotate around Y axis (yaw)
        const cosY = Math.cos(yaw);
        const sinY = Math.sin(yaw);
        const x1 = v.x * cosY - v.z * sinY;
        const z1 = v.x * sinY + v.z * cosY;
        
        // Rotate around X axis (pitch)
        const cosX = Math.cos(pitch);
        const sinX = Math.sin(pitch);
        const y2 = v.y * cosX - z1 * sinX;
        const z2 = v.y * sinX + z1 * cosX;
        
        // Apply position
        return new Vector(
            x1 + transform.position.x,
            y2 + transform.position.y,
            z2 + transform.position.z
        );
    }
    
    // Auto-load map function with loading screen
    LoadMapFromName(mapName) {
        this.showLoadingScreen();
        
        fetch(`${this.base}${this.game}/maps/${mapName}.map?v=${Date.now()}`)
            .then(r => r.json())
            .then(async data => {
                await this.preloadMapAssets(data);
                this.LoadMap(data);
                this.hideLoadingScreen();
                console.log(`Auto-loaded map: ${mapName}`);
            })
            .catch(err => {
                this.hideLoadingScreen();
                console.warn(`Failed to auto-load map: ${mapName}`, err);
            });
    }
    
    async preloadMapAssets(mapData) {
        const textures = new Set();
        
        // Collect brush textures
        mapData.brushes?.forEach(brush => {
            if (brush.texture && !brush.texture.startsWith('#')) {
                textures.add(`${this.base}${this.game}/materials/${brush.texture}`);
            }
        });
        
        // Preload entity textures
        this.entities.forEach(entity => {
            if (entity.preloadTextures) {
                entity.preloadTextures().forEach(tex => textures.add(tex));
            }
        });
        
        // Load all textures
        const textureArray = Array.from(textures);
        for (let i = 0; i < textureArray.length; i++) {
            await this.preloadTexture(textureArray[i]);
            this.loadingScreen.progress = (i + 1) / textureArray.length;
        }
    }
    
    preloadTexture(url) {
        return new Promise((resolve) => {
            if (this.textures.has(url)) {
                resolve();
                return;
            }
            
            const img = new Image();
            img.onload = () => {
                this.loadTexture(url);
                resolve();
            };
            img.onerror = () => resolve(); // Continue even if texture fails
            img.src = url;
        });
    }
    
    // Save binds to localStorage
    saveBinds() {
        localStorage.setItem(`crengine_binds_${this.game}`, JSON.stringify(this.keyBinds));
    }
    
    // Load binds from localStorage
    loadBinds() {
        const saved = localStorage.getItem(`crengine_binds_${this.game}`);
        if (saved) {
            try {
                this.keyBinds = JSON.parse(saved);
            } catch (e) {
                console.warn('Failed to load saved binds:', e);
            }
        }
    }
    
    // Set default bind if not already set
    setDefaultBind(key, command) {
        const k = key.toLowerCase();
        if (!this.keyBinds[k]) {
            this.keyBinds[k] = command;
            this.saveBinds();
        }
    }
    
    // Set default hostname if not already set
    setDefaultHostname(hostname) {
        if (this.GetConVar('hostname') === 'CRENGINE Server') {
            this.hostname.value = hostname;
        }
    }

    // Utility functions
    Msg(text) { 
        console.log(text);
        if (this.consoleOpen) this.addToConsole(text);
    }
    UTIL_RESET() { 
        const stack = new Error().stack;
        this.Msg(`Game Reset! Called from: ${stack.split('\n')[2]?.trim() || 'unknown'}`); 
        this.entities = []; 
        this.uiElements = []; 
        this.brushes = [];
        this.physics.bodies = [];
        this.camera.position = new Vector(0, 0, -10);
        this.camera.rotation = new Vector(0, 0, 0);
        if (this.network.connected) this.network.players.clear();
        // Only auto-reload game code if explicitly requested (not from automatic resets)
        if (gameCode && !this.resetting && this.autoReloadGameCode !== false) {
            this.resetting = true;
            try {
                new Function("ENGINE", gameCode)(this);
            } catch (e) {
                showError(e);
            }
            this.resetting = false;
        }
    }
    UTIL_REMOVE(entity) { this.entities = this.entities.filter(e => e !== entity); }
    
    // UI Methods
    addUIText(id, text, x, y, options = {}) {
        this.uiElements.push({
            id, type: 'text', text, x, y,
            color: options.color || 'white',
            font: options.font || '16px Arial',
            visible: true
        });
    }
    
    addUIRect(id, x, y, width, height, color = 'white') {
        this.uiElements.push({
            id, type: 'rect', x, y, width, height, color, visible: true
        });
    }
    
    addUIButton(id, text, x, y, width, height, onClick, options = {}) {
        this.uiElements.push({
            id, type: 'button', text, x, y, width, height, onClick,
            bgColor: options.bgColor || '#333',
            textColor: options.textColor || 'white',
            borderColor: options.borderColor || '#fff',
            font: options.font || '16px Arial',
            visible: true
        });
    }
    
    removeUIElement(id) {
        this.uiElements = this.uiElements.filter(e => e.id !== id);
    }
    
    getUIElement(id) {
        return this.uiElements.find(e => e.id === id);
    }
    
    // Loading screen methods
    showLoadingScreen() {
        this.loading = true;
        this.loadingScreen.progress = 0;
    }
    
    hideLoadingScreen() {
        this.loading = false;
    }
    
    setLoadingScreen(options) {
        Object.assign(this.loadingScreen, options);
    }
    
    // Entity texture preloading
    preloadEntityTextures(textures) {
        textures.forEach(url => {
            this.preloadQueue.push(url);
        });
    }
    
    // UI System
    drawGUI() {
        this.ctx.clearRect(0, 0, this.guiCanvas.width, this.guiCanvas.height);
        
        // Update pointer events based on visible UI elements
        this.updateGUIPointerEvents();
        
        // Draw loading screen if active
        if (this.loading) {
            this.drawLoadingScreen();
            return;
        }
        
        // Draw custom UI elements
        this.uiElements.forEach(element => {
            if (element.visible) this.drawUIElement(element);
        });
        
        // Debug info
        if (this.debug && this.ctx) {
            this.ctx.fillStyle = "white";
            this.ctx.font = "12px monospace";
            this.ctx.fillText(`Entities: ${this.entities.length}`, 10, 20);
        
            const frameTime = performance.now() - this.lastTime;
            const fps = frameTime > 0 ? Math.min(Math.round(1000 / frameTime), 999) : 0;
            const addplus = fps === 999 ? "+" : "";
            this.ctx.fillText(`FPS: ${fps}${addplus}`, 10, 35);
        
            if (this.camera.position) {
                this.ctx.fillText(`Pos: ${this.camera.position.x.toFixed(1)}, ${this.camera.position.y.toFixed(1)}, ${this.camera.position.z.toFixed(1)}`, 10, 65);
            }
            if (this.camera.rotation) {
                this.ctx.fillText(`Rot: ${this.camera.rotation.x.toFixed(1)}, ${this.camera.rotation.y.toFixed(1)}, ${this.camera.rotation.z.toFixed(1)}`, 10, 80);
            }
        }
    }
    
    drawLoadingScreen() {
        const ctx = this.ctx;
        const w = this.guiCanvas.width;
        const h = this.guiCanvas.height;
        
        // Background
        ctx.fillStyle = this.loadingScreen.background;
        ctx.fillRect(0, 0, w, h);
        
        // Loading text
        ctx.fillStyle = this.loadingScreen.textColor;
        ctx.font = this.loadingScreen.font;
        const textWidth = ctx.measureText(this.loadingScreen.text).width;
        ctx.fillText(this.loadingScreen.text, (w - textWidth) / 2, h / 2 - 50);
        
        // Progress bar
        if (this.loadingScreen.showProgress) {
            const barWidth = 300;
            const barHeight = 20;
            const barX = (w - barWidth) / 2;
            const barY = h / 2;
            
            // Progress bar background
            ctx.fillStyle = '#333';
            ctx.fillRect(barX, barY, barWidth, barHeight);
            
            // Progress bar fill
            ctx.fillStyle = '#0f0';
            ctx.fillRect(barX, barY, barWidth * this.loadingScreen.progress, barHeight);
            
            // Progress text
            const percent = Math.round(this.loadingScreen.progress * 100);
            ctx.fillStyle = this.loadingScreen.textColor;
            ctx.font = '16px Arial';
            const percentText = `${percent}%`;
            const percentWidth = ctx.measureText(percentText).width;
            ctx.fillText(percentText, (w - percentWidth) / 2, barY + 40);
        }
    }
    
    drawUIElement(element) {
        this.ctx.save();
        
        switch (element.type) {
            case 'text':
                this.ctx.fillStyle = element.color || 'white';
                this.ctx.font = element.font || '16px Arial';
                // Handle multi-line text
                if (element.text.includes('\n')) {
                    const lines = element.text.split('\n');
                    lines.forEach((line, index) => {
                        this.ctx.fillText(line, element.x, element.y + (index * 16));
                    });
                } else {
                    this.ctx.fillText(element.text, element.x, element.y);
                }
                break;
                
            case 'rect':
                this.ctx.fillStyle = element.color || 'white';
                this.ctx.fillRect(element.x, element.y, element.width, element.height);
                break;
                
            case 'button':
                this.ctx.fillStyle = element.bgColor || '#333';
                this.ctx.fillRect(element.x, element.y, element.width, element.height);
                this.ctx.strokeStyle = element.borderColor || '#fff';
                this.ctx.strokeRect(element.x, element.y, element.width, element.height);
                this.ctx.fillStyle = element.textColor || 'white';
                this.ctx.font = element.font || '16px Arial';
                const textX = element.x + element.width / 2 - this.ctx.measureText(element.text).width / 2;
                const textY = element.y + element.height / 2 + 6;
                this.ctx.fillText(element.text, textX, textY);
                break;
        }
        
        this.ctx.restore();
    }

    // 3D projection with camera rotation
    project3D(pos) {
        const cx = this.canvas.width / 2;
        const cy = this.canvas.height / 2;
        const f = cx / Math.tan(this.camera.fov * Math.PI / 360);
        
        // Translate relative to camera
        let d = pos.subtract(this.camera.position);
        
        // Apply camera rotation
        const yaw = this.camera.rotation.y * Math.PI / 180;
        const pitch = this.camera.rotation.x * Math.PI / 180;
        
        // Rotate around Y axis (yaw)
        const cosY = Math.cos(yaw);
        const sinY = Math.sin(yaw);
        const x1 = d.x * cosY - d.z * sinY;
        const z1 = d.x * sinY + d.z * cosY;
        
        // Rotate around X axis (pitch)
        const cosX = Math.cos(pitch);
        const sinX = Math.sin(pitch);
        const y2 = d.y * cosX - z1 * sinX;
        const z2 = d.y * sinX + z1 * cosX;
        
        if (z2 <= Math.max(this.camera.near, 0.01)) return null; // Behind near plane
        
        return new Vector(
            cx + (x1 * f) / z2,
            cy - (y2 * f) / z2,
            z2
        );
    }
    
    drawLine3D(pos1, pos2) {
        const p1 = this.project3D(pos1);
        const p2 = this.project3D(pos2);
        
        if (p1 && p2) {
            this.ctx.beginPath();
            this.ctx.moveTo(p1.x, p1.y);
            this.ctx.lineTo(p2.x, p2.y);
            this.ctx.stroke();
        }
    }
    
    // Clip triangle against near plane
    clipTriangle(v1, v2, v3) {
        const near = this.camera.near;

        const verts = [
            this.toCameraSpace(v1),
            this.toCameraSpace(v2),
            this.toCameraSpace(v3)
        ];

        const inside = [];
        const outside = [];

        const effectiveNear = Math.max(near, 0.01);
        verts.forEach(v => {
            if (v.z >= effectiveNear) inside.push(v);
            else outside.push(v);
        });

        // Fully clipped
        if (inside.length === 0) return [];

        // Fully visible
        if (inside.length === 3) return [[inside[0], inside[1], inside[2]]];

        // One inside → one triangle
        if (inside.length === 1 && outside.length === 2) {
            const i = inside[0];
            const o1 = outside[0];
            const o2 = outside[1];

            const t1 = (near - i.z) / (o1.z - i.z);
            const t2 = (near - i.z) / (o2.z - i.z);

            return [[
                i,
                { x: i.x + t1 * (o1.x - i.x), y: i.y + t1 * (o1.y - i.y), z: near },
                { x: i.x + t2 * (o2.x - i.x), y: i.y + t2 * (o2.y - i.y), z: near }
            ]];
        }

        // Two inside → quad → two triangles
        if (inside.length === 2 && outside.length === 1) {
            const [i1, i2] = inside;
            const o = outside[0];

            const t1 = (near - i1.z) / (o.z - i1.z);
            const t2 = (near - i2.z) / (o.z - i2.z);

            const v1 = { x: i1.x + t1 * (o.x - i1.x), y: i1.y + t1 * (o.y - i1.y), z: near };
            const v2 = { x: i2.x + t2 * (o.x - i2.x), y: i2.y + t2 * (o.y - i2.y), z: near };

            return [
                [i1, i2, v1],
                [i2, v2, v1]
            ];
        }

        return [];
    }

    toCameraSpace(pos) {
        let d = pos.subtract(this.camera.position);
        
        const yaw = this.camera.rotation.y * Math.PI / 180;
        const pitch = this.camera.rotation.x * Math.PI / 180;
        
        const cosY = Math.cos(yaw);
        const sinY = Math.sin(yaw);
        const x1 = d.x * cosY - d.z * sinY;
        const z1 = d.x * sinY + d.z * cosY;
        
        const cosX = Math.cos(pitch);
        const sinX = Math.sin(pitch);
        const y2 = d.y * cosX - z1 * sinX;
        const z2 = d.y * sinX + z1 * cosX;
        
        return { x: x1, y: y2, z: z2 };
    }
    
    drawTriangle3D(v1, v2, v3, color = "white", shading = 1) {
        // Backface culling in camera space
        const ca = this.toCameraSpace(v1);
        const cb = this.toCameraSpace(v2);
        const cc = this.toCameraSpace(v3);
        
        const abx = cb.x - ca.x, aby = cb.y - ca.y, abz = cb.z - ca.z;
        const acx = cc.x - ca.x, acy = cc.y - ca.y, acz = cc.z - ca.z;
        const normalX = aby * acz - abz * acy;
        const normalY = abz * acx - abx * acz;
        const normalZ = abx * acy - aby * acx;
        
        const centerX = (ca.x + cb.x + cc.x) / 3;
        const centerY = (ca.y + cb.y + cc.y) / 3;
        const centerZ = (ca.z + cb.z + cc.z) / 3;
        
        const dot = normalX * (-centerX) + normalY * (-centerY) + normalZ * (-centerZ);
        if (dot <= 0) return; // Skip back-facing triangles
        
        const clippedTris = this.clipTriangle(v1, v2, v3);
        if (!clippedTris || clippedTris.length === 0) return;

        const cx = this.canvas.width / 2;
        const cy = this.canvas.height / 2;
        const f = cx / Math.tan(this.camera.fov * Math.PI / 360);

        let shadedColor = color;
        if (color.startsWith('#')) {
            const clamp = v => Math.max(0, Math.min(255, v));
            const r = clamp(parseInt(color.slice(1, 3), 16) * shading);
            const g = clamp(parseInt(color.slice(3, 5), 16) * shading);
            const b = clamp(parseInt(color.slice(5, 7), 16) * shading);
            shadedColor = `rgb(${r|0}, ${g|0}, ${b|0})`;
        }

        this.ctx.fillStyle = shadedColor;
        this.ctx.strokeStyle = shadedColor;
        this.ctx.lineWidth = 1;

        for (const tri of clippedTris) {
            const projected = tri.map(v => ({
                x: cx + (v.x * f) / v.z,
                y: cy - (v.y * f) / v.z
            }));

            this.ctx.beginPath();
            this.ctx.moveTo(projected[0].x, projected[0].y);
            this.ctx.lineTo(projected[1].x, projected[1].y);
            this.ctx.lineTo(projected[2].x, projected[2].y);
            this.ctx.closePath();
            this.ctx.fill();
            this.ctx.stroke();
        }
    }

    
    // Apply rotation to a vertex
    rotateVertex(vertex, rotation, center) {
        let v = vertex.subtract(center);
        
        // Rotate around Y axis (yaw)
        const yaw = rotation.y * Math.PI / 180;
        const cosY = Math.cos(yaw);
        const sinY = Math.sin(yaw);
        const x1 = v.x * cosY - v.z * sinY;
        const z1 = v.x * sinY + v.z * cosY;
        
        // Rotate around X axis (pitch)
        const pitch = rotation.x * Math.PI / 180;
        const cosX = Math.cos(pitch);
        const sinX = Math.sin(pitch);
        const y2 = v.y * cosX - z1 * sinX;
        const z2 = v.y * sinX + z1 * cosX;
        
        // Rotate around Z axis (roll)
        const roll = rotation.z * Math.PI / 180;
        const cosZ = Math.cos(roll);
        const sinZ = Math.sin(roll);
        const x3 = x1 * cosZ - y2 * sinZ;
        const y3 = x1 * sinZ + y2 * cosZ;
        
        return center.add(new Vector(x3, y3, z2));
    }
    
    drawCube3D(transform, ent) {
        const pos = new Vector(transform.position.x, transform.position.y, transform.position.z);
        const scale = transform.scale;
        const rotation = transform.rotation;
        
        // Base cube vertices (unrotated)
        const baseVertices = [
            new Vector(-scale.x, -scale.y, -scale.z), new Vector(scale.x, -scale.y, -scale.z),
            new Vector(scale.x, scale.y, -scale.z), new Vector(-scale.x, scale.y, -scale.z),
            new Vector(-scale.x, -scale.y, scale.z), new Vector(scale.x, -scale.y, scale.z),
            new Vector(scale.x, scale.y, scale.z), new Vector(-scale.x, scale.y, scale.z)
        ];
        
        // Apply rotation and position
        const v = baseVertices.map(vertex => 
            this.rotateVertex(vertex, rotation, new Vector(0, 0, 0)).add(pos)
        );
        
        // Create faces with z-depth for sorting
        const faces = [
            {verts: [v[0],v[3],v[2]], shade: 0.6}, {verts: [v[0],v[2],v[1]], shade: 0.6}, // back
            {verts: [v[4],v[5],v[6]], shade: 1.0}, {verts: [v[4],v[6],v[7]], shade: 1.0}, // front
            {verts: [v[0],v[1],v[5]], shade: 0.4}, {verts: [v[0],v[5],v[4]], shade: 0.4}, // bottom
            {verts: [v[2],v[3],v[7]], shade: 0.9}, {verts: [v[2],v[7],v[6]], shade: 0.9}, // top
            {verts: [v[0],v[4],v[7]], shade: 0.7}, {verts: [v[0],v[7],v[3]], shade: 0.7}, // left
            {verts: [v[1],v[2],v[6]], shade: 0.8}, {verts: [v[1],v[6],v[5]], shade: 0.8}  // right
        ];
        
        // Add faces to render queue
        faces.forEach(({verts: [a,b,c], shade}) => {
            this.renderQueue.push({
                type: 'triangle',
                verts: [a, b, c],
                color: ent.CRE.color || "#888888",
                shade
            });
        });
    }

    // Animation system
    updateAnimations(dt) {
        this.entities.forEach(ent => {
            // Entity transform animation
            if (ent.CRE && ent.CRE.animation) {
                const anim = ent.CRE.animation;
                if (anim.playing) {
                    anim.time += dt * anim.speed;
                    
                    if (anim.loop && anim.time > anim.duration) {
                        anim.time = anim.time % anim.duration;
                    } else if (anim.time >= anim.duration) {
                        anim.time = anim.duration;
                        anim.playing = false;
                    }
                    
                    this.applyAnimation(ent, anim);
                }
            }
            
            // Model animation
            if (ent.CRE && ent.CRE.modelAnimation) {
                const anim = ent.CRE.modelAnimation;
                if (anim.playing) {
                    anim.time += dt * anim.speed;
                    
                    if (anim.loop && anim.time > anim.duration) {
                        anim.time = anim.time % anim.duration;
                    } else if (anim.time >= anim.duration) {
                        anim.time = anim.duration;
                        anim.playing = false;
                    }
                }
            }
        });
    }
    
    applyAnimation(entity, anim) {
        const t = anim.time;
        const keyframes = anim.keyframes;
        
        if (keyframes.length < 2) return;
        
        // Find current keyframe pair
        let k1 = keyframes[0];
        let k2 = keyframes[keyframes.length - 1];
        
        for (let i = 0; i < keyframes.length - 1; i++) {
            if (t >= keyframes[i].time && t <= keyframes[i + 1].time) {
                k1 = keyframes[i];
                k2 = keyframes[i + 1];
                break;
            }
        }
        
        // Interpolate between keyframes
        const alpha = k2.time === k1.time ? 0 : (t - k1.time) / (k2.time - k1.time);
        
        if (k1.position && k2.position) {
            entity.CRE.transform.position = this.lerpVector(k1.position, k2.position, alpha);
        }
        if (k1.rotation && k2.rotation) {
            entity.CRE.transform.rotation = this.lerpVector(k1.rotation, k2.rotation, alpha);
        }
        if (k1.scale && k2.scale) {
            entity.CRE.transform.scale = this.lerpVector(k1.scale, k2.scale, alpha);
        }
    }
    
    lerpVector(v1, v2, t) {
        return new Vector(
            v1.x + (v2.x - v1.x) * t,
            v1.y + (v2.y - v1.y) * t,
            v1.z + (v2.z - v1.z) * t
        );
    }
    
    // Animation control methods
    playAnimation(entity, name) {
        if (entity.CRE && entity.CRE.animations && entity.CRE.animations[name]) {
            entity.CRE.animation = { ...entity.CRE.animations[name], playing: true, time: 0 };
        }
    }
    
    playModelAnimation(entity, name) {
        if (!entity.CRE || !entity.CRE.model) return;
        const model = this.getModel(entity.CRE.model);
        if (model && model.animations && model.animations[name]) {
            entity.CRE.modelAnimation = {
                name,
                playing: true,
                time: 0,
                duration: model.animations[name].duration,
                loop: model.animations[name].loop || false,
                speed: 1
            };
        }
    }
    
    stopAnimation(entity) {
        if (entity.CRE && entity.CRE.animation) {
            entity.CRE.animation.playing = false;
        }
    }
    
    stopModelAnimation(entity) {
        if (entity.CRE && entity.CRE.modelAnimation) {
            entity.CRE.modelAnimation.playing = false;
        }
    }
    
    addAnimation(entity, name, keyframes, duration = 1, loop = false, speed = 1) {
        if (!entity.CRE) entity.CRE = {};
        if (!entity.CRE.animations) entity.CRE.animations = {};
        
        entity.CRE.animations[name] = {
            keyframes,
            duration,
            loop,
            speed,
            playing: false,
            time: 0
        };
    }
    
    // Physics system
    addPhysicsBody(entity, mass = 1, bounce = 0.3, friction = 0.7, isCharacter = false) {
        if (!entity.CRE) entity.CRE = {};
        entity.CRE.physics = {
            velocity: new Vector(0, 0, 0),
            mass,
            bounce: isCharacter ? 0 : bounce,
            friction,
            grounded: false,
            isCharacter,
            collider: { type: 'box', size: entity.CRE.transform?.scale || new Vector(1, 1, 1) }
        };
        this.physics.bodies.push(entity);
    }
    
    updatePhysics(dt) {
        this.physics.bodies.forEach(entity => {
            if (!entity.CRE.physics || !entity.CRE.transform) return;
            
            const physics = entity.CRE.physics;
            const transform = entity.CRE.transform;
            
            // Apply gravity
            if (!physics.grounded) {
                physics.velocity = physics.velocity.add(this.physics.gravity.multiply(dt));
            }
            
            // Apply velocity
            let newPos = transform.position.add(physics.velocity.multiply(dt));
            
            // Check collisions
            const collision = this.checkCollisions(entity, newPos);
            if (collision) {
                if (physics.isCharacter) {
                    // Character physics - no bounce, slide along walls
                    if (collision.normal.y > 0.7) { // Ground collision
                        physics.grounded = true;
                        physics.velocity.y = Math.max(0, physics.velocity.y);
                        newPos = new Vector(newPos.x, collision.point.y + physics.collider.size.y, newPos.z);
                    } else {
                        // Wall collision - slide along wall
                        const dot = physics.velocity.dot(collision.normal);
                        physics.velocity = physics.velocity.subtract(collision.normal.multiply(dot));
                        // Keep original position for walls
                        newPos = transform.position;
                    }
                } else {
                    // Regular physics - bounce
                    if (collision.normal.y > 0.7) { // Ground collision
                        physics.grounded = true;
                        physics.velocity.y = Math.max(0, physics.velocity.y);
                        newPos = new Vector(newPos.x, collision.point.y + physics.collider.size.y, newPos.z);
                    } else {
                        // Wall collision - bounce
                        const dot = physics.velocity.dot(collision.normal);
                        physics.velocity = physics.velocity.subtract(collision.normal.multiply(dot * (1 + physics.bounce)));
                        // Keep original position for walls
                        newPos = transform.position;
                    }
                }
                
                // Apply friction
                physics.velocity = physics.velocity.multiply(1 - physics.friction * dt);
            } else {
                physics.grounded = false;
            }
            
            transform.setPosition(newPos.x, newPos.y, newPos.z);
        });
    }
    
    checkCollisions(entity, newPos) {
        const physics = entity.CRE.physics;
        const size = physics.collider.size;
        
        // Check against brushes (world geometry)
        if (this.brushes) {
            for (const brush of this.brushes) {
                for (const face of brush.faces) {
                    if (face.length >= 3) {
                        const v1 = brush.vertices[face[0]];
                        const v2 = brush.vertices[face[1]];
                        const v3 = brush.vertices[face[2]];
                        
                        // Simple AABB vs triangle collision
                        const collision = this.boxTriangleCollision(newPos, size, v1, v2, v3);
                        if (collision) return collision;
                    }
                }
            }
        }
        
        // Check against other physics bodies
        for (const other of this.physics.bodies) {
            if (other === entity || !other.CRE.physics || !other.CRE.transform) continue;
            
            const collision = this.boxBoxCollision(newPos, size, other.CRE.transform.position, other.CRE.physics.collider.size);
            if (collision) return collision;
        }
        
        return null;
    }
    
    boxTriangleCollision(boxPos, boxSize, v1, v2, v3) {
        // Simple collision: check if box bottom is below triangle plane
        const normal = v2.subtract(v1).cross(v3.subtract(v1)).normalize();
        const d = -normal.dot(v1);
        const boxBottom = boxPos.y - boxSize.y;
        const planeY = (-d - normal.x * boxPos.x - normal.z * boxPos.z) / normal.y;
        
        if (boxBottom <= planeY) {
            // Check if box is within triangle bounds (simplified)
            const center = new Vector((v1.x + v2.x + v3.x) / 3, (v1.y + v2.y + v3.y) / 3, (v1.z + v2.z + v3.z) / 3);
            const dist = boxPos.distance(center);
            if (dist < boxSize.length()) {
                return {
                    point: new Vector(boxPos.x, planeY, boxPos.z),
                    normal: normal
                };
            }
        }
        return null;
    }
    
    boxBoxCollision(pos1, size1, pos2, size2) {
        const dx = Math.abs(pos1.x - pos2.x);
        const dy = Math.abs(pos1.y - pos2.y);
        const dz = Math.abs(pos1.z - pos2.z);
        
        if (dx < (size1.x + size2.x) && dy < (size1.y + size2.y) && dz < (size1.z + size2.z)) {
            // Calculate collision normal (simplified)
            const normal = pos1.subtract(pos2).normalize();
            return {
                point: pos1,
                normal: normal
            };
        }
        return null;
    }
    
    boxModelCollision(boxPos, boxSize, modelEntity) {
        if (!modelEntity.CRE || !modelEntity.CRE.model || !modelEntity.CRE.transform) return null;
        
        const model = this.getModel(modelEntity.CRE.model);
        if (!model) return null;
        
        const transform = modelEntity.CRE.transform;
        
        // Check each face of the model
        for (const obj of model.objects) {
            for (const face of obj.faces) {
                if (face.length >= 3) {
                    for (let i = 1; i < face.length - 1; i++) {
                        const v1Idx = face[0][0] - 1;
                        const v2Idx = face[i][0] - 1;
                        const v3Idx = face[i + 1][0] - 1;
                        
                        if (v1Idx >= 0 && v2Idx >= 0 && v3Idx >= 0 && 
                            v1Idx < obj.vertices.length && v2Idx < obj.vertices.length && v3Idx < obj.vertices.length) {
                            
                            const v1 = this.transformVertex(obj.vertices[v1Idx], transform);
                            const v2 = this.transformVertex(obj.vertices[v2Idx], transform);
                            const v3 = this.transformVertex(obj.vertices[v3Idx], transform);
                            
                            const collision = this.boxTriangleCollision(boxPos, boxSize, v1, v2, v3);
                            if (collision) return collision;
                        }
                    }
                }
            }
        }
        
        return null;
    }

    // Main game loop
    gameLoop(now) {
        if (gameHalted) return;
        
        const dt = (now - this.lastTime) / 1000;
        this.lastTime = now;

        // update any convar stuff
        this.camera.fov = parseFloat(this.GetConVar("fov")) || 60;
        this.camera.near = parseFloat(this.GetConVar("near_plane")) || 0.01;
        this.camera.far = parseFloat(this.GetConVar("far_plane")) || 1000;
        
        // Update animations
        this.updateAnimations(dt);
        
        // Update physics
        // this.updatePhysics(dt);

        // Update entities
        this.entities.forEach(ent => {
            if (ent.update) {
                try {
                    // Only skip if it's explicitly a remote multiplayer entity
                    const cre = ent.CRE;
                    if (this.network.connected && cre && cre.multiplayer && cre.isClient === false) {
                        return;
                    }
                    ent.update(dt, this);
                } catch (e) {
                    if (!gameHalted) showError(e);
                }
            }
        });
        
        // Reset mouse delta after entities have used it
        this.mouseDelta.x = 0;
        this.mouseDelta.y = 0;
        
        // Broadcast entity updates (throttled)
        if (this.network.connected && now % 100 < 16) {
            this.broadcastEntityUpdate();
        }
        
        // Check script triggers
        this.checkTriggers();

        // Clear and prepare render queue
        this.gl.clearColor(0.13, 0.13, 0.13, 1.0);
        this.gl.clear(this.gl.COLOR_BUFFER_BIT | this.gl.DEPTH_BUFFER_BIT);
        
        this.renderQueue = [];
        this.intersectionGrid.clear();
        
        // Add brushes to render queue
        if (this.brushes) {
            this.brushes.forEach(brush => {
                brush.faces.forEach(face => {
                    for (let i = 1; i < face.length - 1; i++) {
                        const v1 = brush.vertices[face[0]];
                        const v2 = brush.vertices[face[i]];
                        const v3 = brush.vertices[face[i + 1]];
                        
                        // Calculate world space normal for lighting
                        const ab = v2.subtract(v1);
                        const ac = v3.subtract(v1);
                        const worldNormal = ab.cross(ac).normalize();
                        
                        // Calculate lighting in world space
                        const triangleCenter = new Vector(
                            (v1.x + v2.x + v3.x) / 3,
                            (v1.y + v2.y + v3.y) / 3,
                            (v1.z + v2.z + v3.z) / 3
                        );
                        const shade = this.calculateLighting(triangleCenter, worldNormal);
                        
                        // Backface culling in camera space
                        const ca = this.toCameraSpace(v1);
                        const cb = this.toCameraSpace(v2);
                        const cc = this.toCameraSpace(v3);
                        
                        const abx = cb.x - ca.x, aby = cb.y - ca.y, abz = cb.z - ca.z;
                        const acx = cc.x - ca.x, acy = cc.y - ca.y, acz = cc.z - ca.z;
                        const normalX = aby * acz - abz * acy;
                        const normalY = abz * acx - abx * acz;
                        const normalZ = abx * acy - aby * acx;
                        
                        const centerX = (ca.x + cb.x + cc.x) / 3;
                        const centerY = (ca.y + cb.y + cc.y) / 3;
                        const centerZ = (ca.z + cb.z + cc.z) / 3;
                        
                        const dot = normalX * (-centerX) + normalY * (-centerY) + normalZ * (-centerZ);
                        
                        if (dot > 0) {
                            
                            const centroidZ = (ca.z + cb.z + cc.z) / 3;

                            const isTexture = brush.texture && !brush.texture.startsWith('#');
                            
                            // Generate UVs for brush faces
                            let uvs = null;
                            if (isTexture) {
                                uvs = this.generateBrushFaceUVs(brush, face, i, worldNormal);
                            }
                            
                            this.renderQueue.push({
                                type: 'triangle',
                                verts: [v1, v2, v3],
                                color: brush.color || (isTexture ? "#f0f" : brush.texture) || "#f0f",
                                texture: isTexture ? `${this.base}${this.game}/materials/${brush.texture}` : null,
                                shade,
                                centroidZ,
                                uvs
                            });
                        }
                    }
                });
            });
        }
        
        // Add 3D entities to render queue
        this.entities.forEach(ent => {
            if (ent.CRE.drawmode === undefined || ent.CRE.drawmode === null) {return;};
            if (!(ent.CRE.transform)) {return;};
            if (ent.CRE.dodraw === false) {return;};
            if (ent.CRE.drawmode === this.Enums.DrawMode.CUBE) {
                this.drawCube3D(ent.CRE.transform, ent);
            } else if (ent.CRE.drawmode === this.Enums.DrawMode.MODEL) {
                if (!(ent.CRE.model)) {return;}
                let color = "#f0f";
                if (ent.CRE.color) {
                    color = String(ent.CRE.color);
                }
                const model = this.getModel(ent.CRE.model);
                if (model) this.drawModel(model, ent.CRE.transform, color, ent.CRE.modelAnimation);
            }
        });
        
        // Add centroid Z to triangles that don't have it
        this.renderQueue.forEach(item => {
            if (item.type === 'triangle' && item.centroidZ === undefined) {
                const ca = this.toCameraSpace(item.verts[0]);
                const cb = this.toCameraSpace(item.verts[1]);
                const cc = this.toCameraSpace(item.verts[2]);
                item.centroidZ = (ca.z + cb.z + cc.z) / 3;
            }
        });
        
        // Build intersection grid (only if many triangles)
        if (this.renderQueue.length > 100) {
            this.renderQueue.forEach((item, index) => {
                if (item.type === 'triangle') {
                    this.addTriangleToGrid(item, index);
                }
            });
            
            // Find and resolve intersections
            const intersections = this.findIntersectingTriangles();
            if (intersections.length > 0) {
                this.resolveIntersections(intersections);
            }
        }
        
        // Sort by centroid Z depth (back to front)
        this.renderQueue.sort((a, b) => b.centroidZ - a.centroidZ);
        
        // Render shadow map first
        if (this.shadows.enabled) {
            this.renderShadowMap();
        }
        
        this.renderWebGL();
        
        // Draw GUI
        this.drawGUI();

        requestAnimationFrame(this.gameLoop.bind(this));
    }

    getModel(modelname) {
        return this.models[modelname] || null; 
    }
    
    loadTexture(url) {
        // if doesn't start with {baseurl}{gamename}/materials, then put that there
        if (!url.startsWith(`${this.base}${this.game}/materials/`)) {
            url = `${this.base}${this.game}/materials/${url}`;
        }
        if (this.textures.has(url)) {
            return this.textures.get(url);
        }
        
        const gl = this.gl;
        const texture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, texture);
        
        // Fill with 1x1 blue pixel while loading for debugging
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array([0, 0, 255, 255]));
        
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = () => {
            gl.bindTexture(gl.TEXTURE_2D, texture);
            gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        };
        image.onerror = () => {
            console.warn('Failed to load texture:', url);
            // Keep the blue placeholder on error
        };
        
        console.log('Loading texture:', url);
        image.src = url;
        
        this.textures.set(url, texture);
        return texture;
    }
    
    // Enhanced triangle intersection system with spatial partitioning
    addTriangleToGrid(triangle, index) {
        const bounds = this.getTriangleBounds(triangle);
        const minX = Math.floor(bounds.min.x / this.gridSize);
        const maxX = Math.floor(bounds.max.x / this.gridSize);
        const minZ = Math.floor(bounds.min.z / this.gridSize);
        const maxZ = Math.floor(bounds.max.z / this.gridSize);
        
        for (let x = minX; x <= maxX; x++) {
            for (let z = minZ; z <= maxZ; z++) {
                const key = `${x},${z}`;
                if (!this.intersectionGrid.has(key)) {
                    this.intersectionGrid.set(key, []);
                }
                this.intersectionGrid.get(key).push({ triangle, index });
            }
        }
    }
    
    getTriangleBounds(triangle) {
        const verts = triangle.verts;
        return {
            min: new Vector(
                Math.min(verts[0].x, verts[1].x, verts[2].x),
                Math.min(verts[0].y, verts[1].y, verts[2].y),
                Math.min(verts[0].z, verts[1].z, verts[2].z)
            ),
            max: new Vector(
                Math.max(verts[0].x, verts[1].x, verts[2].x),
                Math.max(verts[0].y, verts[1].y, verts[2].y),
                Math.max(verts[0].z, verts[1].z, verts[2].z)
            )
        };
    }
    
    findIntersectingTriangles() {
        const intersections = [];
        
        for (const [key, triangles] of this.intersectionGrid) {
            if (triangles.length < 2) continue; // Skip cells with <2 triangles
            
            for (let i = 0; i < triangles.length && i < 10; i++) { // Limit to 10 per cell
                for (let j = i + 1; j < triangles.length && j < 10; j++) {
                    const a = triangles[i];
                    const b = triangles[j];
                    
                    if (this.trianglesIntersect(a.triangle, b.triangle)) {
                        intersections.push({ a: a.index, b: b.index });
                        if (intersections.length > 50) return intersections; // Early exit
                    }
                }
            }
        }
        
        return intersections;
    }
    
    trianglesIntersect(a, b) {
        if (a.type !== 'triangle' || b.type !== 'triangle') return false;
        
        // Fast AABB check first
        const boundsA = this.getTriangleBounds(a);
        const boundsB = this.getTriangleBounds(b);
        
        if (boundsA.max.x < boundsB.min.x || boundsA.min.x > boundsB.max.x ||
            boundsA.max.y < boundsB.min.y || boundsA.min.y > boundsB.max.y ||
            boundsA.max.z < boundsB.min.z || boundsA.min.z > boundsB.max.z) {
            return false;
        }
        
        const [a1, a2, a3] = a.verts;
        const [b1, b2, b3] = b.verts;
        return this.triangleTriangleIntersect(a1, a2, a3, b1, b2, b3);
    }
    
    triangleTriangleIntersect(t1v1, t1v2, t1v3, t2v1, t2v2, t2v3) {
        // Möller-Trumbore algorithm for triangle-triangle intersection
        const n1 = this.crossProduct(this.subtract(t1v2, t1v1), this.subtract(t1v3, t1v1));
        const d1 = -this.dotProduct(n1, t1v1);
        const dist2 = [t2v1, t2v2, t2v3].map(v => this.dotProduct(n1, v) + d1);
        const sign2 = dist2.map(d => Math.sign(d));
        if (sign2[0] === sign2[1] && sign2[1] === sign2[2] && sign2[0] !== 0) return false;
        
        const n2 = this.crossProduct(this.subtract(t2v2, t2v1), this.subtract(t2v3, t2v1));
        const d2 = -this.dotProduct(n2, t2v1);
        const dist1 = [t1v1, t1v2, t1v3].map(v => this.dotProduct(n2, v) + d2);
        const sign1 = dist1.map(d => Math.sign(d));
        if (sign1[0] === sign1[1] && sign1[1] === sign1[2] && sign1[0] !== 0) return false;
        
        // Check if triangles are coplanar
        if (Math.abs(this.dotProduct(n1, n2)) > 0.999) {
            return this.coplanarTriangleIntersect(t1v1, t1v2, t1v3, t2v1, t2v2, t2v3);
        }
        
        return true;
    }
    
    coplanarTriangleIntersect(t1v1, t1v2, t1v3, t2v1, t2v2, t2v3) {
        // Project to 2D and check intersection
        const n = this.crossProduct(this.subtract(t1v2, t1v1), this.subtract(t1v3, t1v1));
        const absN = { x: Math.abs(n.x), y: Math.abs(n.y), z: Math.abs(n.z) };
        
        let coord = 0;
        if (absN.y > absN.x) coord = 1;
        if (absN.z > (coord === 0 ? absN.x : absN.y)) coord = 2;
        
        const project = (v) => {
            if (coord === 0) return { x: v.y, y: v.z };
            if (coord === 1) return { x: v.x, y: v.z };
            return { x: v.x, y: v.y };
        };
        
        const t1 = [project(t1v1), project(t1v2), project(t1v3)];
        const t2 = [project(t2v1), project(t2v2), project(t2v3)];
        
        return this.triangles2DIntersect(t1, t2);
    }
    
    triangles2DIntersect(t1, t2) {
        // Check if any edge of t1 intersects any edge of t2
        for (let i = 0; i < 3; i++) {
            for (let j = 0; j < 3; j++) {
                if (this.segments2DIntersect(
                    t1[i], t1[(i + 1) % 3],
                    t2[j], t2[(j + 1) % 3]
                )) {
                    return true;
                }
            }
        }
        
        // Check if any vertex of t1 is inside t2 or vice versa
        return t1.some(v => this.pointInTriangle2D(v, t2)) ||
               t2.some(v => this.pointInTriangle2D(v, t1));
    }
    
    segments2DIntersect(a1, a2, b1, b2) {
        const det = (a2.x - a1.x) * (b2.y - b1.y) - (a2.y - a1.y) * (b2.x - b1.x);
        if (Math.abs(det) < 1e-10) return false;
        
        const t = ((b1.x - a1.x) * (b2.y - b1.y) - (b1.y - a1.y) * (b2.x - b1.x)) / det;
        const u = ((b1.x - a1.x) * (a2.y - a1.y) - (b1.y - a1.y) * (a2.x - a1.x)) / det;
        
        return t >= 0 && t <= 1 && u >= 0 && u <= 1;
    }
    
    pointInTriangle2D(p, tri) {
        const [a, b, c] = tri;
        const v0 = { x: c.x - a.x, y: c.y - a.y };
        const v1 = { x: b.x - a.x, y: b.y - a.y };
        const v2 = { x: p.x - a.x, y: p.y - a.y };
        
        const dot00 = v0.x * v0.x + v0.y * v0.y;
        const dot01 = v0.x * v1.x + v0.y * v1.y;
        const dot02 = v0.x * v2.x + v0.y * v2.y;
        const dot11 = v1.x * v1.x + v1.y * v1.y;
        const dot12 = v1.x * v2.x + v1.y * v2.y;
        
        const invDenom = 1 / (dot00 * dot11 - dot01 * dot01);
        const u = (dot11 * dot02 - dot01 * dot12) * invDenom;
        const v = (dot00 * dot12 - dot01 * dot02) * invDenom;
        
        return (u >= 0) && (v >= 0) && (u + v <= 1);
    }
    
    compareIntersectingTriangles(a, b) {
        const aCentroid = this.getTriangleCentroid(a.verts);
        const bCentroid = this.getTriangleCentroid(b.verts);
        const aDepth = this.toCameraSpace(aCentroid).z;
        const bDepth = this.toCameraSpace(bCentroid).z;
        return bDepth - aDepth;
    }
    
    getTriangleCentroid(verts) {
        const [v1, v2, v3] = verts;
        return new Vector((v1.x + v2.x + v3.x) / 3, (v1.y + v2.y + v3.y) / 3, (v1.z + v2.z + v3.z) / 3);
    }
    
    crossProduct(a, b) {
        return { x: a.y * b.z - a.z * b.y, y: a.z * b.x - a.x * b.z, z: a.x * b.y - a.y * b.x };
    }
    
    dotProduct(a, b) {
        return a.x * b.x + a.y * b.y + a.z * b.z;
    }
    
    subtract(a, b) {
        return { x: a.x - b.x, y: a.y - b.y, z: a.z - b.z };
    }
    
    resolveIntersections(intersections) {
        // Simple depth-based resolution for intersecting triangles
        intersections.forEach(({ a, b }) => {
            const triA = this.renderQueue[a];
            const triB = this.renderQueue[b];
            
            if (triA && triB && triA.centroidZ === triB.centroidZ) {
                // Add small offset to break ties
                triB.centroidZ += 0.001;
            }
        });
    }
    
    renderWebGL() {
        if (!this.programInfo) return;
        
        const gl = this.gl;
        const programInfo = this.programInfo;
        
        gl.viewport(0, 0, this.canvas.width, this.canvas.height);
        
        // Create matrices
        const projectionMatrix = this.createProjectionMatrix();
        const modelViewMatrix = this.createModelViewMatrix();
        
        // Separate textured and non-textured triangles
        const coloredTriangles = [];
        const texturedTriangles = [];
        
        this.renderQueue.forEach(item => {
            if (item.type === 'triangle') {
                if (item.texture) {
                    texturedTriangles.push(item);
                } else {
                    coloredTriangles.push(item);
                }
            }
        });
        
        // Render colored triangles
        if (coloredTriangles.length > 0) {
            this.renderColoredTriangles(coloredTriangles, projectionMatrix, modelViewMatrix);
        }
        
        // Render textured triangles
        if (texturedTriangles.length > 0) {
            this.renderTexturedTriangles(texturedTriangles, projectionMatrix, modelViewMatrix);
        }
    }
    
    renderColoredTriangles(triangles, projectionMatrix, modelViewMatrix) {
        const gl = this.gl;
        const programInfo = this.programInfo;
        
        const vertices = [];
        const colors = [];
        
        triangles.forEach(item => {
            const [v1, v2, v3] = item.verts;
            
            vertices.push(
                v1.x, v1.y, v1.z,
                v2.x, v2.y, v2.z,
                v3.x, v3.y, v3.z
            );
            
            const color = this.hexToRgb(item.color);
            const corrected = this.applyColorCorrection(color.r, color.g, color.b);
            const r = (corrected.r / 255) * item.shade;
            const g = (corrected.g / 255) * item.shade;
            const b = (corrected.b / 255) * item.shade;
            
            colors.push(
                r, g, b, r, g, b, r, g, b
            );
        });
        
        const positionBuffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertices), gl.STATIC_DRAW);
        
        const colorBuffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, colorBuffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(colors), gl.STATIC_DRAW);
        
        gl.useProgram(programInfo.program);
        gl.uniformMatrix4fv(programInfo.uniformLocations.projectionMatrix, false, projectionMatrix);
        gl.uniformMatrix4fv(programInfo.uniformLocations.modelViewMatrix, false, modelViewMatrix);
        gl.uniform1i(programInfo.uniformLocations.uUseTexture, false);
        
        gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
        gl.vertexAttribPointer(programInfo.attribLocations.vertexPosition, 3, gl.FLOAT, false, 0, 0);
        gl.enableVertexAttribArray(programInfo.attribLocations.vertexPosition);
        
        gl.bindBuffer(gl.ARRAY_BUFFER, colorBuffer);
        gl.vertexAttribPointer(programInfo.attribLocations.vertexColor, 3, gl.FLOAT, false, 0, 0);
        gl.enableVertexAttribArray(programInfo.attribLocations.vertexColor);
        
        gl.disableVertexAttribArray(programInfo.attribLocations.textureCoord);
        
        gl.drawArrays(gl.TRIANGLES, 0, vertices.length / 3);
        
        gl.deleteBuffer(positionBuffer);
        gl.deleteBuffer(colorBuffer);
    }
    
    renderTexturedTriangles(triangles, projectionMatrix, modelViewMatrix) {
        const gl = this.gl;
        const programInfo = this.programInfo;
        
        // Group by texture
        const textureGroups = new Map();
        triangles.forEach(item => {
            if (!textureGroups.has(item.texture)) {
                textureGroups.set(item.texture, []);
            }
            textureGroups.get(item.texture).push(item);
        });
        
        textureGroups.forEach((items, textureUrl) => {
            const vertices = [];
            const colors = [];
            const texCoords = [];
            
            items.forEach(item => {
                const [v1, v2, v3] = item.verts;
                
                vertices.push(
                    v1.x, v1.y, v1.z,
                    v2.x, v2.y, v2.z,
                    v3.x, v3.y, v3.z
                );
                
                // For textured models, use white with shading
                const shade = item.shade || 1.0;
                colors.push(
                    shade, shade, shade,
                    shade, shade, shade,
                    shade, shade, shade
                );
                
                // Use UVs if available, otherwise default
                if (item.uvs && item.uvs.length === 3) {
                    texCoords.push(
                        item.uvs[0][0], 1.0 - item.uvs[0][1],
                        item.uvs[1][0], 1.0 - item.uvs[1][1],
                        item.uvs[2][0], 1.0 - item.uvs[2][1]
                    );
                } else {
                    texCoords.push(
                        0, 1, 1, 1, 0.5, 0
                    );
                }
            });
            
            const positionBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertices), gl.STATIC_DRAW);
            
            const colorBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, colorBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(colors), gl.STATIC_DRAW);
            
            const texCoordBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, texCoordBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(texCoords), gl.STATIC_DRAW);
            
            gl.useProgram(programInfo.program);
            gl.uniformMatrix4fv(programInfo.uniformLocations.projectionMatrix, false, projectionMatrix);
            gl.uniformMatrix4fv(programInfo.uniformLocations.modelViewMatrix, false, modelViewMatrix);
            gl.uniform1i(programInfo.uniformLocations.uUseTexture, true);
            
            // Set base color from first item (all items in group have same texture)
            const baseColor = this.hexToRgb(items[0].color);
            const corrected = this.applyColorCorrection(baseColor.r, baseColor.g, baseColor.b);
            gl.uniform3f(programInfo.uniformLocations.uBaseColor, corrected.r / 255, corrected.g / 255, corrected.b / 255);
            
            const texture = this.loadTexture(textureUrl);
            gl.activeTexture(gl.TEXTURE0);
            gl.bindTexture(gl.TEXTURE_2D, texture);
            gl.uniform1i(programInfo.uniformLocations.uSampler, 0);
            
            gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
            gl.vertexAttribPointer(programInfo.attribLocations.vertexPosition, 3, gl.FLOAT, false, 0, 0);
            gl.enableVertexAttribArray(programInfo.attribLocations.vertexPosition);
            
            gl.bindBuffer(gl.ARRAY_BUFFER, colorBuffer);
            gl.vertexAttribPointer(programInfo.attribLocations.vertexColor, 3, gl.FLOAT, false, 0, 0);
            gl.enableVertexAttribArray(programInfo.attribLocations.vertexColor);
            
            gl.bindBuffer(gl.ARRAY_BUFFER, texCoordBuffer);
            gl.vertexAttribPointer(programInfo.attribLocations.textureCoord, 2, gl.FLOAT, false, 0, 0);
            gl.enableVertexAttribArray(programInfo.attribLocations.textureCoord);
            
            gl.drawArrays(gl.TRIANGLES, 0, vertices.length / 3);
            
            gl.deleteBuffer(positionBuffer);
            gl.deleteBuffer(colorBuffer);
            gl.deleteBuffer(texCoordBuffer);
        });

    }
    
    createProjectionMatrix() {
        const fov = this.camera.fov * Math.PI / 180;
        const aspect = this.canvas.width / this.canvas.height;
        const near = this.camera.near;
        const far = this.camera.far;
        
        const f = 1.0 / Math.tan(fov / 2);
        const rangeInv = 1 / (near - far);
        
        return new Float32Array([
            f / aspect, 0, 0, 0,
            0, f, 0, 0,
            0, 0, (near + far) * rangeInv, -1,
            0, 0, near * far * rangeInv * 2, 0
        ]);
    }
    
    createModelViewMatrix() {
        // Create view matrix from camera
        const pos = this.camera.position;
        const rot = this.camera.rotation;
        
        // Convert rotation to radians (inverted for view matrix)
        const pitch = rot.x * Math.PI / 180;
        const yaw = -rot.y * Math.PI / 180;
        
        // Simplified rotation matrix (pitch and yaw only)
        const cosPitch = Math.cos(pitch);
        const sinPitch = Math.sin(pitch);
        const cosYaw = Math.cos(yaw);
        const sinYaw = Math.sin(yaw);
        
        // Combined rotation matrix (Y then X)
        const m00 = cosYaw;
        const m01 = sinYaw * sinPitch;
        const m02 = sinYaw * cosPitch;
        const m10 = 0;
        const m11 = cosPitch;
        const m12 = -sinPitch;
        const m20 = -sinYaw;
        const m21 = cosYaw * sinPitch;
        const m22 = cosYaw * cosPitch;
        
        // Apply translation (inverse of camera position)
        const tx = -(m00 * pos.x + m10 * pos.y + m20 * pos.z);
        const ty = -(m01 * pos.x + m11 * pos.y + m21 * pos.z);
        const tz = -(m02 * pos.x + m12 * pos.y + m22 * pos.z);
        
        return new Float32Array([
            m00, m01, m02, 0,
            m10, m11, m12, 0,
            m20, m21, m22, 0,
            tx, ty, tz, 1
        ]);
    }
    
    generateBrushFaceUVs(brush, face, triangleIndex, normal) {
        // Map whole texture to each face
        if (face.length === 4) {
            // Quad face - map texture to corners
            const quadUVs = [[0, 1], [1, 1], [1, 0], [0, 0]];
            
            if (triangleIndex === 1) {
                // First triangle: 0,1,2
                return [quadUVs[0], quadUVs[1], quadUVs[2]];
            } else {
                // Second triangle: 0,2,3  
                return [quadUVs[0], quadUVs[2], quadUVs[3]];
            }
        } else {
            // Triangle face
            return [[0, 1], [1, 1], [0.5, 0]];
        }
    }
    
    hexToRgb(color) {
        if (!color) return { r: 255, g: 0, b: 255 };
        
        // If it's not a hex color (e.g., texture path), use white
        if (!color.startsWith('#')) return { r: 255, g: 255, b: 255 };
        
        // Handle 3-digit hex (#f0f -> #ff00ff)
        if (color.length === 4) {
            const r = color[1];
            const g = color[2];
            const b = color[3];
            return {
                r: parseInt(r + r, 16),
                g: parseInt(g + g, 16),
                b: parseInt(b + b, 16)
            };
        }
        
        // Handle 8-digit hex (RGBA - ignore alpha)
        if (color.length === 9) {
            return {
                r: parseInt(color.slice(1, 3), 16),
                g: parseInt(color.slice(3, 5), 16),
                b: parseInt(color.slice(5, 7), 16)
            };
        }
        
        // Handle 6-digit hex
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(color);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : { r: 255, g: 0, b: 255 };
    }
    
    applyColorCorrection(r, g, b) {
        const brightness = parseFloat(this.GetConVar("r_brightness")) || 1.0;
        const contrast = parseFloat(this.GetConVar("r_contrast")) || 1.0;
        const saturation = parseFloat(this.GetConVar("r_saturation")) || 1.0;
        const gamma = parseFloat(this.GetConVar("r_gamma")) || 2.2;
        
        // Normalize to 0-1
        r /= 255; g /= 255; b /= 255;
        
        // Brightness
        r *= brightness; g *= brightness; b *= brightness;
        
        // Contrast
        r = (r - 0.5) * contrast + 0.5;
        g = (g - 0.5) * contrast + 0.5;
        b = (b - 0.5) * contrast + 0.5;
        
        // Saturation
        const gray = 0.299 * r + 0.587 * g + 0.114 * b;
        r = gray + saturation * (r - gray);
        g = gray + saturation * (g - gray);
        b = gray + saturation * (b - gray);
        
        // Gamma
        r = Math.pow(Math.max(0, r), 1.0 / gamma);
        g = Math.pow(Math.max(0, g), 1.0 / gamma);
        b = Math.pow(Math.max(0, b), 1.0 / gamma);
        
        // Clamp and convert back to 0-255
        return {
            r: Math.max(0, Math.min(255, r * 255)),
            g: Math.max(0, Math.min(255, g * 255)),
            b: Math.max(0, Math.min(255, b * 255))
        };
    }
    
    calculateLighting(position, normal) {
        let totalLight = 0.2; // Ambient light
        
        // Get all light entities
        const lights = this.entities.filter(ent => 
            ent.constructor.name === 'Light' || 
            ent.constructor.name === 'LightSpot' || 
            ent.constructor.name === 'LightSun'
        );
        // if no lights found, just make fullbright
        if (lights.length === 0) return 1.0;
        lights.forEach(light => {
            if (light.constructor.name === 'LightSun') {
                // Directional light
                const lightDir = light.CRE.direction.normalize();
                const dot = normal.dot(lightDir.multiply(-1));
                totalLight += Math.max(0, dot) * light.CRE.intensity;
            } else {
                // Point light or spot light
                const lightPos = light.CRE.transform.position;
                const lightDir = lightPos.subtract(position).normalize();
                const distance = lightPos.distance(position);
                
                if (distance < light.CRE.range) {
                    const attenuation = 1 - (distance / light.CRE.range);
                    const dot = normal.dot(lightDir);
                    let intensity = Math.max(0, dot) * light.CRE.intensity * attenuation;
                    
                    // Spot light cone check
                    if (light.constructor.name === 'LightSpot') {
                        const spotDir = light.CRE.transform.getForwardVector();
                        const spotDot = lightDir.multiply(-1).dot(spotDir);
                        const coneAngle = Math.cos(light.CRE.angle * Math.PI / 180);
                        if (spotDot < coneAngle) intensity = 0;
                    }
                    
                    totalLight += intensity;
                }
            }
        });
        
        return Math.min(1.0, totalLight);
    }
    
    // Raycasting for physics and gameplay
    raycast(origin, direction, maxDistance = 1000) {
        const hits = [];
        
        // Check against brushes
        if (this.brushes) {
            this.brushes.forEach(brush => {
                brush.faces.forEach(face => {
                    if (face.length >= 3) {
                        const v1 = brush.vertices[face[0]];
                        const v2 = brush.vertices[face[1]];
                        const v3 = brush.vertices[face[2]];
                        
                        const hit = this.rayTriangleIntersect(origin, direction, v1, v2, v3);
                        if (hit && hit.distance <= maxDistance) {
                            hits.push({ ...hit, object: brush });
                        }
                    }
                });
            });
        }
        
        // Sort by distance and return closest
        hits.sort((a, b) => a.distance - b.distance);
        return hits[0] || null;
    }
    
    rayTriangleIntersect(origin, direction, v1, v2, v3) {
        const edge1 = v2.subtract(v1);
        const edge2 = v3.subtract(v1);
        const h = direction.cross(edge2);
        const a = edge1.dot(h);
        
        if (a > -0.00001 && a < 0.00001) return null;
        
        const f = 1.0 / a;
        const s = origin.subtract(v1);
        const u = f * s.dot(h);
        
        if (u < 0.0 || u > 1.0) return null;
        
        const q = s.cross(edge1);
        const v = f * direction.dot(q);
        
        if (v < 0.0 || u + v > 1.0) return null;
        
        const t = f * edge2.dot(q);
        
        if (t > 0.00001) {
            const point = origin.add(direction.multiply(t));
            const normal = edge1.cross(edge2).normalize();
            return { point, normal, distance: t };
        }
        
        return null;
    }
    
    // Shadow mapping system
    initShadowMapping() {
        const gl = this.gl;
        
        // Ensure shadows object exists
        if (!this.shadows) {
            this.shadows = {
                enabled: false,
                mapSize: 1024,
                framebuffer: null,
                texture: null,
                lightMatrix: null
            };
        }
        
        try {
            // Check for depth texture support
            const depthExt = gl.getExtension('WEBGL_depth_texture');
            if (!depthExt) {
                console.warn('Shadow mapping not supported: WEBGL_depth_texture extension not available');
                this.shadows.enabled = false;
                return;
            }
            
            // Create shadow framebuffer
            this.shadows.framebuffer = gl.createFramebuffer();
            this.shadows.texture = gl.createTexture();
            
            gl.bindTexture(gl.TEXTURE_2D, this.shadows.texture);
            gl.texImage2D(gl.TEXTURE_2D, 0, gl.DEPTH_COMPONENT, this.shadows.mapSize, this.shadows.mapSize, 0, gl.DEPTH_COMPONENT, gl.UNSIGNED_SHORT, null);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
            gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
            
            gl.bindFramebuffer(gl.FRAMEBUFFER, this.shadows.framebuffer);
            gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.DEPTH_ATTACHMENT, gl.TEXTURE_2D, this.shadows.texture, 0);
            
            // Check framebuffer status
            if (gl.checkFramebufferStatus(gl.FRAMEBUFFER) !== gl.FRAMEBUFFER_COMPLETE) {
                console.warn('Shadow mapping framebuffer incomplete');
                this.shadows.enabled = false;
                gl.bindFramebuffer(gl.FRAMEBUFFER, null);
                return;
            }
            
            // Create shadow shader
            const shadowVS = `
                attribute vec4 aVertexPosition;
                uniform mat4 uLightMatrix;
                void main() {
                    gl_Position = uLightMatrix * aVertexPosition;
                }
            `;
            
            const shadowFS = `
                precision mediump float;
                void main() {
                    gl_FragColor = vec4(gl_FragCoord.z, 0.0, 0.0, 1.0);
                }
            `;
            
            this.shadowProgram = this.createShaderProgram(gl, shadowVS, shadowFS);
            
            gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        } catch (e) {
            console.warn('Shadow mapping initialization failed:', e);
            this.shadows.enabled = false;
        }
    }
    
    renderShadowMap() {
        if (!this.shadows.enabled || !this.shadows.framebuffer) return;
        
        const gl = this.gl;
        
        // Find main light for shadows
        const mainLight = this.entities.find(ent => ent.constructor.name === 'LightSun');
        if (!mainLight) return;
        
        // Create light view matrix
        const lightPos = mainLight.CRE.direction.multiply(-50);
        this.shadows.lightMatrix = this.createLightMatrix(lightPos, new Vector(0, 0, 0));
        
        // Render to shadow map
        gl.bindFramebuffer(gl.FRAMEBUFFER, this.shadows.framebuffer);
        gl.viewport(0, 0, this.shadows.mapSize, this.shadows.mapSize);
        gl.clear(gl.DEPTH_BUFFER_BIT);
        
        gl.useProgram(this.shadowProgram);
        gl.uniformMatrix4fv(gl.getUniformLocation(this.shadowProgram, 'uLightMatrix'), false, this.shadows.lightMatrix);
        
        // Disable unused vertex attributes
        gl.disableVertexAttribArray(this.programInfo.attribLocations.vertexColor);
        gl.disableVertexAttribArray(this.programInfo.attribLocations.textureCoord);
        
        // Render shadow casters (simplified)
        this.renderQueue.forEach(item => {
            if (item.type === 'triangle') {
                const vertices = [item.verts[0].x, item.verts[0].y, item.verts[0].z,
                                item.verts[1].x, item.verts[1].y, item.verts[1].z,
                                item.verts[2].x, item.verts[2].y, item.verts[2].z];
                
                const buffer = gl.createBuffer();
                gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
                gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertices), gl.STATIC_DRAW);
                
                const posLoc = gl.getAttribLocation(this.shadowProgram, 'aVertexPosition');
                gl.vertexAttribPointer(posLoc, 3, gl.FLOAT, false, 0, 0);
                gl.enableVertexAttribArray(posLoc);
                
                gl.drawArrays(gl.TRIANGLES, 0, 3);
                gl.deleteBuffer(buffer);
            }
        });
        
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        gl.viewport(0, 0, this.canvas.width, this.canvas.height);
    }
    
    createLightMatrix(lightPos, target) {
        // Simplified light projection matrix
        const size = 20;
        const near = 1;
        const far = 100;
        
        // Orthographic projection for directional light
        const ortho = new Float32Array([
            2/size, 0, 0, 0,
            0, 2/size, 0, 0,
            0, 0, -2/(far-near), 0,
            0, 0, -(far+near)/(far-near), 1
        ]);
        
        // Look-at matrix (simplified)
        const view = this.createLookAtMatrix(lightPos, target, new Vector(0, 1, 0));
        
        return this.multiplyMatrices(ortho, view);
    }
    
    createLookAtMatrix(eye, target, up) {
        const f = target.subtract(eye).normalize();
        const s = f.cross(up).normalize();
        const u = s.cross(f);
        
        return new Float32Array([
            s.x, u.x, -f.x, 0,
            s.y, u.y, -f.y, 0,
            s.z, u.z, -f.z, 0,
            -s.dot(eye), -u.dot(eye), f.dot(eye), 1
        ]);
    }
    
    multiplyMatrices(a, b) {
        const result = new Float32Array(16);
        for (let i = 0; i < 4; i++) {
            for (let j = 0; j < 4; j++) {
                result[i * 4 + j] = a[i * 4] * b[j] + a[i * 4 + 1] * b[4 + j] + 
                                   a[i * 4 + 2] * b[8 + j] + a[i * 4 + 3] * b[12 + j];
            }
        }
        return result;
    }
    
    // Real LAN Multiplayer via WebSocket
    startLANServer() {
        this.network.isHost = true;
        this.network.roomId = Math.random().toString(36).substring(2, 8).toUpperCase();
        this.connectToGameServer();
    }
    
    connectToLAN(roomCode) {
        if (!roomCode) {
            this.addToConsole('Usage: connect <room_code>');
            return;
        }
        
        this.network.roomId = roomCode.toUpperCase();
        this.network.isHost = false;
        this.connectToGameServer();
    }
    
    connectToGameServer() {
        this.addToConsole('Connecting to game server...');
        
        // Get username from crz.games if available
        this.fetchUsername().then(user => {
            if (user) {
                this.network.playerId = user[0];
                this.network.user = user[1];
            } else {
                this.network.playerId = `inauth_${Math.random().toString(36).substring(2, 10)}`;
                this.network.user = null;
            }
            this.initWebSocketConnection();
        }).catch(() => {
            this.network.playerId = Math.random().toString(36).substring(2, 10);
            this.network.user = null;
            this.initWebSocketConnection();
        });
    }
    
    async fetchUsername() {
        if (!this.crzgames) return null;
        
        try {
            const response = await fetch('https://crz.games:21212/endpoints/getloggedinuser.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success && data.authenticated) {
                return [data.username, data.user];
            }
            return null;
        } catch (e) {
            return null;
        }
    }
    
    initWebSocketConnection() {
        try {
            this.network.socket = new WebSocket('wss://crz.games:21212/gameserver');
            
            this.network.socket.onopen = () => {
                try {
                    this.network.connected = true;
                    this.addToConsole(`Connected! Room: ${this.network.roomId}`);
                    
                    // Get gameId from URL parameter
                    const urlParams = new URLSearchParams(window.location.search);
                    const gameIdParam = urlParams.get('gameId');
                    const gameId = !isNaN(parseInt(gameIdParam)) ? parseInt(gameIdParam) : null;
                    
                    // Join or create room
                    this.sendMessage({
                        type: this.network.isHost ? 'create_room' : 'join_room',
                        roomId: this.network.roomId,
                        playerId: this.network.playerId,
                        hostname: this.GetConVar('hostname'),
                        maxPlayers: parseInt(this.GetConVar('sv_maxplayers')),
                        gameId: gameId,
                        hostUserId: this.network.user?.id || null
                    });
                    
                    if (this.network.isHost) {
                        this.addToConsole(`Room created: ${this.network.roomId}`);
                        this.addToConsole('Share this code with other players');
                    } else {
                        this.addToConsole(`Joined room: ${this.network.roomId}`);
                    }
                } catch (e) {
                    console.error('Error in socket.onopen:', e);
                }
            };
            
            this.network.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleNetworkMessage(data);
                } catch (e) {
                    console.error('Error handling network message:', e);
                }
            };
            
            this.network.socket.onclose = () => {
                this.network.connected = false;
                this.addToConsole('Disconnected from server');
            };
            
            this.network.socket.onerror = (event) => {
                console.error('WebSocket error:', event);
                this.addToConsole('Failed to connect to game server');
            };
        } catch (e) {
            console.error('Connection error:', e);
            this.addToConsole(`Connection failed: ${e.message}`);
        }
    }
    
    getGameState() {
        return {
            entities: this.entities.filter(e => e.CRE?.networkSync).map(e => ({
                id: e.networkId, type: e.constructor.name,
                position: e.CRE.transform?.getPosition(), rotation: e.CRE.transform?.getRotation()
            }))
        };
    }
    
    syncGameState(state) {
        // Sync entities from server
        state.entities?.forEach(entData => {
            let ent = this.entities.find(e => e.networkId === entData.id);
            if (!ent) {
                ent = this.SpawnEntity(entData.type);
                if (ent) ent.networkId = entData.id;
            }
            if (ent && ent.CRE.transform) {
                if (entData.position) ent.CRE.transform.setPosition(...Object.values(entData.position));
                if (entData.rotation) ent.CRE.transform.setRotation(...Object.values(entData.rotation));
            }
        });
    }
    
    disconnectFromServer() {
        if (this.network.socket) {
            this.network.socket.close();
            this.network.socket = null;
            this.network.connected = false;
            this.network.players.clear();
        }
    }
    
    sendMessage(data) {
        if (this.network.connected && this.network.socket) {
            data.roomId = this.network.roomId;
            data.playerId = this.network.playerId;
            this.network.socket.send(JSON.stringify(data));
        }
    }
    
    handleNetworkMessage(data) {
        switch (data.type) {
            case 'room_created':
                this.addToConsole('Room created successfully');
                break;
            case 'room_joined':
                this.addToConsole('Joined room successfully');
                break;
            case 'player_joined':
                this.addChatMessage(`${data.playerId} joined.`)
                this.addToConsole(`${data.playerId} joined the room`);
                break;
            case 'player_left':
                this.addChatMessage(`${data.playerId} disconnected.`)
                this.addToConsole(`${data.playerId} left the room`);
                // Remove the player's entity
                const entityToRemove = this.entities.find(e => 
                    e.CRE && e.CRE.multiplayer && 
                    e.networkId === data.playerId
                );
                if (entityToRemove) {
                    this.UTIL_REMOVE(entityToRemove);
                }
                this.network.players.delete(data.playerId);
                break;
            case 'entity_update':
                this.updateNetworkEntity(data);
                break;
            case 'player_update':
                this.updateNetworkEntity(data);
                break;
            case 'chat_message':
                this.addChatMessage(`${data.displayName || data.playerId || 'Unknown'} : ${data.message}`);
                break;
            case 'game_state':
                this.syncGameState(data.state);
                break;
        }
    }
    
    updateNetworkEntity(data) {
        // Don't update our own entity
        if (data.playerId === this.network.playerId) return;
        
        // Find or create the network entity
        let entity = this.entities.find(e => 
            e.CRE && e.CRE.multiplayer && 
            e.networkId === data.playerId
        );
        
        if (!entity) {
            // Create new network entity
            entity = this.SpawnEntity(data.entityType);
            if (entity && entity.CRE) {
                entity.networkId = data.playerId;
                entity.CRE.multiplayer = true;
                entity.CRE.isClient = false;
                
                // Apply networked defaults for new network entities
                if (entity.CRE.networkedDefaults) {
                    Object.keys(entity.CRE.networkedDefaults).forEach(varPath => {
                        this.setNestedProperty(entity, varPath, entity.CRE.networkedDefaults[varPath]);
                    });
                }
            }
        }
        
        if (entity && entity.CRE) {
            // Update networked variables
            if (data.networkedData) {
                Object.keys(data.networkedData).forEach(varPath => {
                    this.setNestedProperty(entity, varPath, data.networkedData[varPath]);
                });
            }
        }
    }
    
    broadcastEntityUpdate() {
        if (!this.network.connected) return;
        
        // Find multiplayer entities that belong to this client
        this.entities.forEach(ent => {
            if (ent.CRE && ent.CRE.multiplayer && ent.CRE.isClient) {
                const networkedData = {};
                if (ent.CRE.networkedVars) {
                    ent.CRE.networkedVars.forEach(varPath => {
                        const value = this.getNestedProperty(ent, varPath);
                        if (value !== undefined) {
                            networkedData[varPath] = value;
                        }
                    });
                }
                
                // Find registered entity type
                let entityType = ent.constructor.name;
                if (this.EntityClasses) {
                    for (const [registeredName, entityData] of Object.entries(this.EntityClasses)) {
                        if (entityData.cls === ent.constructor) {
                            entityType = registeredName;
                            break;
                        }
                    }
                }
                
                this.sendMessage({
                    type: 'entity_update',
                    entityType: entityType,
                    networkedData: networkedData,
                    timestamp: Date.now()
                });
            }
        });
    }
    
    getNestedProperty(obj, path) {
        return path.split('.').reduce((current, key) => current && current[key], obj);
    }
    
    setNestedProperty(obj, path, value) {
        const keys = path.split('.');
        const lastKey = keys.pop();
        const target = keys.reduce((current, key) => current && current[key], obj);
        
        if (target && lastKey) {
            if (lastKey === 'transform' && value && typeof value === 'object') {
                // Special handling for transform objects
                if (target[lastKey] && target[lastKey].setPosition) {
                    // Update existing transform
                    if (value.position) target[lastKey].setPosition(value.position.x, value.position.y, value.position.z);
                    if (value.rotation) target[lastKey].setRotation(value.rotation.x, value.rotation.y, value.rotation.z);
                    if (value.scale) target[lastKey].setScale(value.scale.x, value.scale.y, value.scale.z);
                } else {
                    // Create new transform
                    target[lastKey] = new Transform();
                    if (value.position) target[lastKey].setPosition(value.position.x, value.position.y, value.position.z);
                    if (value.rotation) target[lastKey].setRotation(value.rotation.x, value.rotation.y, value.rotation.z);
                    if (value.scale) target[lastKey].setScale(value.scale.x, value.scale.y, value.scale.z);
                }
            } else {
                target[lastKey] = value;
            }
        }
    }
    
    // Scripting system
    executeScript(code) {
        try {
            const context = {
                CRE: this,
                entities: this.entities,
                camera: this.camera,
                vars: this.scripts.variables,
                spawn: (type, ...args) => this.SpawnEntity(type, ...args),
                find: (name) => this.entities.find(e => e.name === name),
                log: (msg) => this.addToConsole(msg),
                Vector: Vector,
                Transform: Transform
            };
            
            const func = new Function(...Object.keys(context), code);
            const result = func(...Object.values(context));
            
            if (result !== undefined) {
                this.addToConsole(`Result: ${result}`);
            }
        } catch (e) {
            this.addToConsole(`Script error: ${e.message}`);
        }
    }
    
    addTrigger(name, condition, action) {
        if (!this.triggers) this.triggers = new Map();
        this.triggers.set(name, { condition, action });
    }
    
    checkTriggers() {
        if (!this.triggers) return;
        
        for (const [name, trigger] of this.triggers) {
            try {
                if (trigger.condition()) {
                    trigger.action();
                    this.triggers.delete(name);
                }
            } catch (e) {
                console.warn(`Trigger ${name} error:`, e);
            }
        }
    }
}

// Light entities
class Light {
    constructor() {
        this.CRE = {
            transform: new Transform(),
            color: "#ffffff",
            intensity: 1.0,
            range: 10.0,
            dodraw: false
        };
    }
}

class LightSpot {
    constructor() {
        this.CRE = {
            transform: new Transform(),
            color: "#ffffff",
            intensity: 1.0,
            range: 15.0,
            angle: 45.0,
            dodraw: false
        };
    }
}

class LightSun {
    constructor() {
        this.CRE = {
            transform: new Transform(),
            color: "#ffff99",
            intensity: 2.0,
            direction: new Vector(0.5, -1, 0.3),
            dodraw: false
        };
    }
}

class PhysicsBox {
    constructor() {
        this.CRE = {
            transform: new Transform(),
            color: "#ff6600",
            drawmode: 0, // CUBE
            dodraw: true
        };
    }
    
    init() {
        // Auto-add physics when spawned
        if (window.CRE) {
            window.CRE.addPhysicsBody(this, 1, 0.5, 0.8);
        }
    }
}

class Transform {
    constructor() {
        this.position = new Vector(0, 0, 0);
        this.rotation = new Vector(0, 0, 0);
        this.scale = new Vector(1, 1, 1);
    }
    getPosition() { return this.position; }
    setPosition(x, y, z) { this.position = new Vector(x, y, z); }
    getRotation() { return this.rotation; }
    setRotation(x, y, z) { this.rotation = new Vector(x, y, z); }
    getScale() { return this.scale; }
    setScale(x, y, z) { this.scale = new Vector(x, y, z); }
    getForwardVector() {
        const yaw = this.rotation.y * Math.PI / 180;
        const pitch = this.rotation.x * Math.PI / 180;
        return new Vector(
            Math.sin(yaw) * Math.cos(pitch),
            Math.sin(pitch),
            -Math.cos(yaw) * Math.cos(pitch)
        );
    }
    getRightVector() {
        const yaw = this.rotation.y * Math.PI / 180;
        return new Vector(
            Math.cos(yaw),
            0,
            Math.sin(yaw)
        );
    }
    getUpVector() {
        return new Vector(0, 1, 0);
    }
}
class Vector {
    constructor(x = 0, y = 0, z = 0) {
        this.x = x;
        this.y = y;
        this.z = z;
    }

    add(v) {
        return new Vector(this.x + v.x, this.y + v.y, this.z + v.z);
    }
    
    dot(v) {
        return this.x * v.x + this.y * v.y + this.z * v.z;
    }
    
    cross(v) {
        return new Vector(
            this.y * v.z - this.z * v.y,
            this.z * v.x - this.x * v.z,
            this.x * v.y - this.y * v.x
        );
    }

    subtract(v) {
        return new Vector(this.x - v.x, this.y - v.y, this.z - v.z);
    }

    multiply(scalar) {
        return new Vector(this.x * scalar, this.y * scalar, this.z * scalar);
    }

    divide(scalar) {
        return new Vector(this.x / scalar, this.y / scalar, this.z / scalar);
    }

    length() {
        return Math.sqrt(this.x * this.x + this.y * this.y + this.z * this.z);
    }

    normalize() {
        const len = this.length();
        if (len === 0) return new Vector(0, 0, 0);
        return this.divide(len);
    }

    average() {
        return (this.x + this.y + this.z) / 3
    }

    distance(v) {
        return Math.sqrt((this.x - v.x) ** 2 + (this.y - v.y) ** 2 + (this.z - v.z) ** 2);
    }

    point(v) {
        // get a Vector that represents a rotation to point toward something, taking in this as a position, and v as a position.
        const direction = v.subtract(this).normalize();
        const yaw = Math.atan2(direction.x, -direction.z) * 180 / Math.PI;
        const horizontalDist = Math.sqrt(direction.x * direction.x + direction.z * direction.z);
        const pitch = Math.atan2(direction.y, horizontalDist) * 180 / Math.PI * 0.2;
        return new Vector(pitch, yaw, 0);
    }
}
// ConVar flags
const FCVAR_NONE = 0;
const FCVAR_CHEAT = 1;
const FCVAR_REPLICATED = 2;
const FCVAR_ARCHIVE = 4;
const FCVAR_NOTIFY = 8;
const FCVAR_USERINFO = 16;
const FCVAR_PROTECTED = 32;
const FCVAR_SPONLY = 64;
const FCVAR_DEMO = 128;
const FCVAR_DONTRECORD = 256;

class ConVar {
    constructor(name, defaultValue, flags, help) {
        this.name = name;
        this.value = defaultValue;
        this.flags = flags || FCVAR_NONE;
        this.help = help || "";
        this.defaultValue = defaultValue || "0";
    }
    
    hasFlag(flag) {
        return (this.flags & flag) !== 0;
    }
    getAsNumber() {
        return parseFloat(this.value);
    }
    getAsString() {
        return String(this.value);
    }
    getAsBoolean() {
        return this.value === '1' || this.value.toLowerCase() === 'true';
    }
    
}

class ConCommand {
    constructor(name, func, flags, help) {
        this.name = name;
        this.func = func;
        this.flags = flags || FCVAR_NONE;
        this.help = help || "";
    }
    hasFlag(flag) {
        return (this.flags & flag) !== 0;
    }
    execute(...args) {
        if (this.func) {
            this.func(...args);
        }
    }
}

const urlParams = new URLSearchParams(window.location.search);
const gameName = urlParams.get('game') || 'game';
// --- Initialize CRE ---
const cre = new CRE(document.getElementById("gameCanvas"), gameName);
console.log("CRENGINE initialized");
console.log("Loading game:", gameName);
window.engine = cre; // Make CRE instance globally available

// Get game name from URL parameter

// if from crz.games, put uploads/games at the start of the path
let baseURL = "";
if (document.referrer.includes("crz.games") || document.referrer.includes("ngrok")) {
    baseURL = "uploads/games/";
    cre.base = "uploads/games/";
}

let gameHalted = false;
let gameCode = null;

function showError(error) {
    const popup = document.getElementById('errorPopup');
    popup.innerHTML = `
        <h3>Runtime Error</h3>
        <pre>${error.message}\n${error.stack}</pre>
        <div>
            <button onclick="continueGame()">Continue</button>
            <button onclick="restartGame()">Restart</button>
            <button onclick="haltGame()">Halt</button>
        </div>
    `;
    popup.style.display = 'block';
}

function continueGame() {
    document.getElementById('errorPopup').style.display = 'none';
    gameHalted = false;
}

function restartGame() {
    document.getElementById('errorPopup').style.display = 'none';
    gameHalted = false;
    cre.UTIL_RESET();
    if (gameCode) {
        try {
            new Function("ENGINE", gameCode)(cre);
        } catch (e) {
            showError(e);
        }
    }
}

function haltGame() {
    gameHalted = true;
    document.getElementById('errorPopup').style.display = 'none';
}

window.onerror = (msg, file, line, col, error) => {
    if (!gameHalted) showError(error || new Error(msg));
    return true;
};

window.addEventListener('unhandledrejection', e => {
    if (!gameHalted) showError(e.reason);
});

const consoleInput = document.getElementById('console-input');
const suggestions = document.getElementById('console-suggestions');
let selectedSuggestionIndex = 0;

consoleInput.addEventListener('input', e => {
    const input = e.target.value;
    if (input.length > 0) {
        const allCommands = [...Object.keys(cre.conCommands), ...Object.keys(cre.conVars)];
        const matches = allCommands.filter(cmd => cmd.startsWith(input)).slice(0, 5);
        
        if (matches.length > 0) {
            suggestions.innerHTML = matches.map(cmd => `<div class="suggestion">${cmd}</div>`).join('');
            suggestions.style.display = 'block';
            
            // Add click handlers
            suggestions.querySelectorAll('.suggestion').forEach(el => {
                el.onclick = () => {
                    consoleInput.value = el.textContent;
                    suggestions.style.display = 'none';
                    consoleInput.focus();
                };
            });
        } else {
            suggestions.style.display = 'none';
        }
    } else {
        suggestions.style.display = 'none';
    }
});

consoleInput.addEventListener('keydown', e => {
    if (e.key === 'Tab') {
        e.preventDefault();

        const items = suggestions.querySelectorAll('.suggestion');
        if (items.length === 0) return;

        selectedSuggestionIndex %= items.length;
        consoleInput.value = items[selectedSuggestionIndex].textContent;
        selectedSuggestionIndex++;
        suggestions.style.display = 'none';
        consoleInput.focus();
        return;
    }

    if (e.key === 'Enter') {
        const command = e.target.value.trim();
        if (command) {
            const parts = command.split(' ');
            cre.ConsoleHandler(parts[0], ...parts.slice(1));
            e.target.value = '';
            suggestions.style.display = 'none';
            selectedSuggestionIndex = 0;
        }
    }
});
