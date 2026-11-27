/**
 * The 3D view's stage (M14, #139): the scene's parts, a camera, its controls, and a frame drawn only when something
 * has changed. It is the picture's second renderer, behind the same interface as the 2D board (SPEC: Ready for 3D).
 *
 * - **On demand.** A frame is drawn when a part, the camera or the canvas's size changes, and while a camera move or a
 *   trick plays; at rest nothing is drawn.
 * - **Paused** while the canvas is off the screen or the tab is hidden: a change waits, and is drawn once it shows.
 * - **The pixel ratio** is the screen's, at most 2, or 1.5 on a device with 4 GB of memory or less.
 * - **A part change swaps that part's group,** and every other part stays as it was.
 * - **Lighting:** two plain lights for the first frame, then the room's environment once it has been drawn.
 * - **The camera** turns to each step's side; drag, the arrow keys, + and −, and the buttons move it.
 * - **Reduced motion:** the camera goes straight to where it is sent, and no trick plays.
 * - **During a trick** the camera holds still: a drag or a key turns nothing, and a camera button waits until the
 *   board lands. A trick lands at rest at once when the canvas leaves the screen, the tab hides or the view closes.
 * - **Closed,** every geometry, material and texture is given back, and the renderer with them.
 * - **A failure** (a lost context, or anything thrown while drawing) closes the stage and says so, so the 2D board
 *   comes back.
 */
import {
	DirectionalLight,
	Group,
	HemisphereLight,
	Mesh,
	PerspectiveCamera,
	Scene as World,
	Vector3,
	type Material,
	type Object3D,
	type Texture,
} from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import type { Place, Renderer, Trick } from '../picture';
import { drawnFrom, part, type Textures } from './parts';
import { ends, mm } from './shapes';
import { SLOTS, type Slot } from './scene';

/** What the stage needs of a renderer: three's WebGLRenderer, or a test's double. */
export interface Drawing {
	domElement: HTMLCanvasElement;
	info: {
		render: { frame: number };
		memory: { geometries: number; textures: number };
	};
	render: ( scene: Object3D, camera: PerspectiveCamera ) => void;
	setPixelRatio: ( ratio: number ) => void;
	setSize: ( width: number, height: number, style?: boolean ) => void;
	dispose: () => void;
}

export interface Needs {
	renderer: Drawing;
	/** A texture at an address, or null where none is loaded; `arrived` is called once it has. */
	texture: ( url: string, arrived: () => void ) => Texture | null;
	/** Whether the shopper's device asks for reduced motion, read at each move. */
	reduced: () => boolean;
	/** Told once, when the stage fails and has closed itself. */
	failed: ( why: unknown ) => void;
	/** The room's lighting, made once the first frame has been drawn; none in a test. */
	environment?: (
		renderer: Drawing
	) => { texture: Texture; dispose: () => void } | null;
	/** How much memory the device says it has, in GB, where it says. */
	memory?: number | undefined;
}

/** How long a camera move takes where motion is allowed, in milliseconds. */
export const MOVE = 500;

/** The lens: a long one, so the board's proportions read true. */
export const FOV = 30;

/** How far the arrow keys turn the camera, and how far + and − zoom. */
export const KEYS = {
	turn: Math.PI / 12,
	tilt: Math.PI / 18,
	zoom: 1.2,
} as const;

/** How far, in CSS pixels, one finger's drag goes before it is a turn of the board or the page's swipe (F14). */
export const SWIPE = 8;

/** The highest pixel ratio drawn, and the highest on a device with little memory. */
export const RATIO = { most: 2, low: 1.5, lowMemory: 4 } as const;

/**
 * The pixel ratio to draw at: the screen's, capped.
 *
 * @param screen The screen's own ratio.
 * @param memory The device's memory in GB, where it says.
 */
export function ratio( screen: number, memory: number | undefined ): number {
	const cap =
		undefined !== memory && memory <= RATIO.lowMemory
			? RATIO.low
			: RATIO.most;
	return Math.min( screen > 0 ? screen : 1, cap );
}

/**
 * An ease in and out, from 0 to 1.
 *
 * @param t The time, from 0 to 1.
 */
export function ease( t: number ): number {
	return t < 0.5 ? 2 * t * t : 1 - ( -2 * t + 2 ) ** 2 / 2;
}

/**
 * Where the camera looks from at a place, as a direction from the board in three's axes (y up, the nose toward +x),
 * and the point it looks at as a share of the deck's half-length toward the nose.
 *
 * @param place The place.
 * @param top   Whether the step shows the top, for the reset.
 */
export function aim(
	place: Place,
	top: boolean
): { from: [ number, number, number ]; at: number; near: number } {
	switch ( place ) {
		case 'top':
			return { from: [ 0, 1, 0.0001 ], at: 0, near: 1 };
		case 'under':
			return { from: [ 0, -1, 0.0001 ], at: 0, near: 1 };
		// Level with the deck's edge, turned 17 degrees toward the nose: both wheels on the near side, and the truck
		// and the riser between each and the deck, show (#171, D-518).
		case 'side':
			return { from: [ 0.3, 0, 1 ], at: 0, near: 1 };
		case 'graphic':
			return { from: [ 0.25, -1, 0.35 ], at: 0.45, near: 0.5 };
		case 'reset':
			return { from: [ 0.55, top ? 0.6 : -0.6, 0.75 ], at: 0, near: 1 };
	}
}

/**
 * How far away the camera must be for the whole deck to fit the canvas, in millimetres, with a margin.
 *
 * @param length The deck's length, in millimetres.
 * @param aspect The canvas's width over its height.
 */
export function distance( length: number, aspect: number ): number {
	const vertical = ( FOV * Math.PI ) / 180;
	const across = 2 * Math.atan( Math.tan( vertical / 2 ) * aspect );
	const fit = Math.min( vertical, across );
	return ( ( length / 2 ) * 1.2 ) / Math.tan( fit / 2 );
}

/**
 * Give back a group's geometries, materials and textures, each once, however many of its meshes share it.
 *
 * @param root The group.
 */
export function release( root: Object3D ): void {
	const given = new Set< { dispose: () => void } >();
	root.traverse( ( node ) => {
		if ( ! ( node instanceof Mesh ) ) {
			return;
		}
		given.add( node.geometry );
		const materials = ( [] as Material[] ).concat( node.material );
		for ( const one of materials ) {
			given.add( one );
			const map = ( one as Material & { map?: Texture | null } ).map;
			// A texture whose image has not arrived waits on its material, not as its map (F1).
			const waiting = one.userData.texture as Texture | null | undefined;
			for ( const texture of [ map, waiting ] ) {
				if ( texture ) {
					given.add( texture );
				}
			}
		}
	} );
	for ( const one of given ) {
		one.dispose();
	}
}

/**
 * Open the stage on a renderer's canvas.
 *
 * @param needs What it draws with, and whom it tells.
 */
export function stage( needs: Needs ): Renderer & {
	/** What a test reads: the frames drawn, the renderer's memory and each slot's group. */
	probe: () => {
		frames: number;
		geometries: number;
		textures: number;
		groups: Partial< Record< Slot, string > >;
		closed: boolean;
		/** Whether a trick plays. */
		playing: boolean;
		/** The board's position, in millimetres, and its turns about x, y and z, in radians, in the scene's axes. */
		board: number[];
		/** The camera's position and the point it turns about, in millimetres, in three's axes (#171). */
		camera: number[];
	};
	/** For a test's picture: the board held at a moment of a trick, or at rest. */
	hold: ( trick: Trick | null, progress: number ) => void;
} {
	const { renderer, reduced } = needs;
	const canvas = renderer.domElement;
	const view = canvas.ownerDocument.defaultView ?? window;
	// No ground of its own: the canvas is clear, and the frame's ground, the builder's, shows through it.
	const world = new World();
	const camera = new PerspectiveCamera( FOV, 1, 10, 20000 );
	// The key light follows the camera, so the side looked at is lit.
	const key = new DirectionalLight( '#ffffff', 1.1 );
	key.position.set( 0.4, 1, 0.6 );
	camera.add( key );
	world.add( new HemisphereLight( '#ffffff', '#8a8780', 0.7 ), camera );
	// The board in the scene's axes, z up, turned into three's, y up; a trick moves this group.
	const board = new Group();
	board.name = 'board';
	const axes = new Group();
	axes.rotation.x = -Math.PI / 2;
	axes.add( board );
	world.add( axes );
	const controls = new OrbitControls( camera, canvas );
	// A vertical swipe on a phone scrolls the page: the controls take a sideways drag and a pinch.
	canvas.style.touchAction = 'pan-y';
	controls.enablePan = false;
	controls.minDistance = 150;
	controls.maxDistance = 4000;

	const drawn = new Map< Slot, { from: string; group: Group | null } >();
	// A texture is drawn once it has arrived; until then, and where it fails, the part's colour shows.
	const textures: Textures = {
		load: ( url, use ) => {
			if ( ! url || closed ) {
				return null;
			}
			const texture = needs.texture( url, () => {
				// An image that arrives after the view has closed is put on nothing.
				if ( closed || ! texture ) {
					return;
				}
				use( texture );
				invalidate();
			} );
			return texture;
		},
	};
	let length = 800;
	let top = false;
	let shown = false;
	let closed = false;
	let frame = 0;
	let visible = true;
	let waiting = false;
	let lit: { texture: Texture; dispose: () => void } | null = null;
	let lighting = 0;
	let move:
		| {
				start: number;
				from: Vector3;
				to: Vector3;
				at: Vector3;
				target: Vector3;
		  }
		| undefined;
	let trick:
		| { start: number; trick: Trick; done: ( played: boolean ) => void }
		| undefined;
	let queue: Promise< boolean > = Promise.resolve( true );
	// Where the camera was last sent, and whether the shopper has moved it since.
	let sentTo: Place = 'reset';
	let touched = false;
	// A camera button pressed while a trick plays, which goes once the board lands.
	let held: Place | undefined;

	/**
	 * Close everything, and say so where it failed.
	 *
	 * @param why What failed.
	 */
	const fail = ( why: unknown ): void => {
		if ( closed ) {
			return;
		}
		close();
		needs.failed( why );
	};

	/**
	 * Ask for a frame: drawn on the next one, unless the canvas is out of sight, where it waits.
	 */
	const invalidate = (): void => {
		// Nothing is drawn before the first scene, nor once closed.
		if ( closed || ! shown ) {
			return;
		}
		if ( ! visible || view.document.hidden ) {
			waiting = true;
			land();
			return;
		}
		if ( 0 === frame ) {
			frame = view.requestAnimationFrame( draw );
		}
	};

	/**
	 * Draw one frame, and ask for the next while a move or a trick is still playing.
	 *
	 * @param now The frame's time.
	 */
	const draw = ( now: number ): void => {
		frame = 0;
		if ( closed ) {
			return;
		}
		try {
			const more = step( now );
			renderer.render( world, camera );
			light();
			if ( more ) {
				invalidate();
			}
		} catch ( error ) {
			fail( error );
		}
	};

	/**
	 * Light the room once the first frame is drawn, on a task of its own, since its first build takes a while.
	 */
	const light = (): void => {
		if ( 0 !== lighting || ! needs.environment ) {
			return;
		}
		lighting = view.setTimeout( () => {
			if ( closed ) {
				return;
			}
			try {
				lit = needs.environment?.( renderer ) ?? null;
				world.environment = lit?.texture ?? null;
				invalidate();
			} catch ( error ) {
				fail( error );
			}
		}, 0 );
	};

	/**
	 * Move the camera and the board on to this frame.
	 *
	 * @param now The frame's time.
	 * @return Whether something is still moving.
	 */
	const step = ( now: number ): boolean => {
		let more = false;
		if ( move ) {
			const t = Math.min( ( now - move.start ) / MOVE, 1 );
			const k = ease( Math.max( t, 0 ) );
			camera.position.lerpVectors( move.from, move.to, k );
			controls.target.lerpVectors( move.at, move.target, k );
			controls.update();
			if ( t >= 1 ) {
				move = undefined;
			} else {
				more = true;
			}
		}
		if ( trick ) {
			const t = Math.min(
				( now - trick.start ) / trick.trick.duration,
				1
			);
			trick.trick.pose( board, Math.max( t, 0 ) );
			if ( t >= 1 ) {
				land();
			} else {
				more = true;
			}
		}
		return more;
	};

	/**
	 * Put the board back at rest.
	 */
	const rest = (): void => {
		board.position.set( 0, 0, 0 );
		board.rotation.set( 0, 0, 0 );
	};

	/**
	 * End the trick that is playing, if one is: the board at rest, the camera free again, and sent where a button
	 * pressed meanwhile asked.
	 */
	const land = (): void => {
		if ( ! trick ) {
			return;
		}
		rest();
		const { done } = trick;
		trick = undefined;
		controls.enabled = true;
		done( true );
		const place = held;
		held = undefined;
		if ( place ) {
			go( place );
		}
	};

	/**
	 * Where the camera goes for a place.
	 *
	 * @param place The place.
	 */
	const placed = ( place: Place ): { position: Vector3; target: Vector3 } => {
		const { from, at, near } = aim( place, top );
		const along = ( length / 2 ) * at;
		const target = new Vector3( along, -20, 0 );
		const far = distance( length * near, camera.aspect );
		return {
			position: new Vector3( ...from )
				.normalize()
				.multiplyScalar( far )
				.add( target ),
			target,
		};
	};

	/**
	 * Send the camera to a place: eased where motion is allowed, at once where it is reduced or nothing is shown yet.
	 *
	 * @param place The place.
	 * @param now   Whether to go at once.
	 */
	const go = ( place: Place, now = false ): void => {
		sentTo = place;
		touched = false;
		const { position, target } = placed( place );
		if ( now || reduced() ) {
			move = undefined;
			camera.position.copy( position );
			controls.target.copy( target );
			controls.update();
			invalidate();
			return;
		}
		move = {
			start: view.performance.now(),
			from: camera.position.clone(),
			to: position,
			at: controls.target.clone(),
			target,
		};
		invalidate();
	};

	let measured = '';
	/**
	 * The canvas's size: the drawing's, at the capped pixel ratio, and the camera's aspect. Where the shopper has not
	 * moved the camera since it was last sent somewhere, it goes there again, fitted to the new shape.
	 */
	const size = (): void => {
		const width = Math.max( 1, canvas.clientWidth );
		const height = Math.max( 1, canvas.clientHeight );
		const pixels = ratio( view.devicePixelRatio, needs.memory );
		// The same size again draws nothing: a resize observer may report one that has not changed.
		const now = `${ width } ${ height } ${ pixels }`;
		if ( now === measured ) {
			return;
		}
		measured = now;
		renderer.setPixelRatio( pixels );
		renderer.setSize( width, height, false );
		camera.aspect = width / height;
		camera.updateProjectionMatrix();
		if ( shown && ! touched && ! move ) {
			go( sentTo, true );
		}
		invalidate();
	};

	const keys = ( event: KeyboardEvent ): void => {
		const moves: Record< string, () => void > = {
			ArrowLeft: () => controls.rotateLeft( KEYS.turn ),
			ArrowRight: () => controls.rotateLeft( -KEYS.turn ),
			ArrowUp: () => controls.rotateUp( KEYS.tilt ),
			ArrowDown: () => controls.rotateUp( -KEYS.tilt ),
			// The controls' dolly out brings the camera in (examples/jsm/controls/OrbitControls.js, `_dollyOut`).
			'+': () => controls.dollyOut( KEYS.zoom ),
			'=': () => controls.dollyOut( KEYS.zoom ),
			'-': () => controls.dollyIn( KEYS.zoom ),
			'−': () => controls.dollyIn( KEYS.zoom ),
		};
		const moved = moves[ event.key ];
		if ( ! moved || event.altKey || event.ctrlKey || event.metaKey ) {
			return;
		}
		event.preventDefault();
		// The camera holds still while a trick plays.
		if ( trick ) {
			return;
		}
		move = undefined;
		touched = true;
		moved();
	};

	const lost = ( event: Event ): void => {
		event.preventDefault();
		fail( event );
	};

	const shows = (): void => {
		if ( ! visible || view.document.hidden ) {
			// A trick landed out of sight is drawn at rest once the canvas shows again.
			if ( trick ) {
				land();
				waiting = true;
			}
			return;
		}
		if ( waiting ) {
			waiting = false;
			invalidate();
		}
	};

	const seen =
		'function' === typeof view.IntersectionObserver
			? new view.IntersectionObserver( ( entries ) => {
					visible = entries.at( -1 )?.isIntersecting ?? visible;
					shows();
				} )
			: null;
	seen?.observe( canvas );
	const sized =
		'function' === typeof view.ResizeObserver
			? new view.ResizeObserver( () => size() )
			: null;
	sized?.observe( canvas );
	view.document.addEventListener( 'visibilitychange', shows );
	canvas.addEventListener( 'keydown', keys );
	canvas.addEventListener( 'webglcontextlost', lost );
	// The shopper's own move ends the camera's.
	const taken = (): void => {
		move = undefined;
		touched = true;
	};
	controls.addEventListener( 'start', taken );
	controls.addEventListener( 'change', invalidate );
	// A finger on the canvas turns the board about its upright alone (F14): Chrome hands the controls a swipe's first
	// moves before it takes the page's pan, which turned the camera as the page scrolled. So under one finger the pitch
	// is held, and a drag is the controls' only once it has gone further sideways than up or down: until it has gone
	// `SWIPE` pixels it is no one's, and one that goes up or down is the page's to the end. Two fingers pinch as before;
	// the buttons and the keys are the stage's own and unchanged.
	const fingers = new Map< number, { x: number; y: number } >();
	// Whether one finger's drag turns the board: not known until it has gone `SWIPE` pixels.
	let turning: boolean | undefined;
	const pitch = (): void => {
		const one = 1 === fingers.size;
		controls.minPolarAngle = one ? controls.getPolarAngle() : 0;
		controls.maxPolarAngle = one ? controls.getPolarAngle() : Math.PI;
		turning = undefined;
	};
	const down = ( event: PointerEvent ): void => {
		if ( 'touch' === event.pointerType ) {
			fingers.set( event.pointerId, {
				x: event.clientX,
				y: event.clientY,
			} );
			pitch();
		}
	};
	const up = ( event: PointerEvent ): void => {
		if ( fingers.delete( event.pointerId ) ) {
			pitch();
		}
	};
	// Ahead of the controls, which listen on the canvas itself: a move held here never reaches them.
	const sideways = ( event: PointerEvent ): void => {
		const from = fingers.get( event.pointerId );
		if ( ! from || 1 !== fingers.size ) {
			return;
		}
		const across = Math.abs( event.clientX - from.x );
		const along = Math.abs( event.clientY - from.y );
		if ( undefined === turning && Math.max( across, along ) >= SWIPE ) {
			turning = across > along;
		}
		if ( ! turning ) {
			event.stopPropagation();
		}
	};
	canvas.addEventListener( 'pointerdown', down );
	for ( const ended of [ 'pointerup', 'pointercancel' ] as const ) {
		canvas.addEventListener( ended, up );
	}
	view.addEventListener( 'pointermove', sideways, { capture: true } );
	size();

	/**
	 * Give everything back: the parts, the lighting, the controls, the renderer and every listener.
	 */
	const close = (): void => {
		if ( closed ) {
			return;
		}
		closed = true;
		view.cancelAnimationFrame( frame );
		view.clearTimeout( lighting );
		frame = 0;
		trick?.done( false );
		trick = undefined;
		seen?.disconnect();
		sized?.disconnect();
		view.document.removeEventListener( 'visibilitychange', shows );
		canvas.removeEventListener( 'keydown', keys );
		canvas.removeEventListener( 'webglcontextlost', lost );
		controls.removeEventListener( 'start', taken );
		controls.removeEventListener( 'change', invalidate );
		canvas.removeEventListener( 'pointerdown', down );
		view.removeEventListener( 'pointermove', sideways, {
			capture: true,
		} );
		for ( const ended of [ 'pointerup', 'pointercancel' ] as const ) {
			canvas.removeEventListener( ended, up );
		}
		controls.dispose();
		for ( const { group } of drawn.values() ) {
			if ( group ) {
				release( group );
			}
		}
		drawn.clear();
		lit?.texture.dispose();
		lit?.dispose();
		world.environment = null;
		renderer.dispose();
	};

	return {
		show( scene, onTop ) {
			if ( closed || ! scene ) {
				return;
			}
			try {
				let changed = false;
				for ( const slot of SLOTS ) {
					const from = drawnFrom( slot, scene );
					const was = drawn.get( slot );
					if ( was?.from === from ) {
						continue;
					}
					changed = true;
					if ( was?.group ) {
						board.remove( was.group );
						release( was.group );
					}
					// An empty slot is noted too, so the next view that leaves it empty changes nothing.
					const group = part( slot, scene, textures );
					if ( group ) {
						board.add( group );
					}
					drawn.set( slot, { from, group } );
				}
				const { nose, tail } = ends( scene.deck );
				length = Math.max( nose - tail, mm( scene.deck.width ) );
				const turned = top !== onTop;
				const first = ! shown;
				top = onTop;
				shown = true;
				if ( first || turned ) {
					go( 'reset', first );
				} else if ( changed ) {
					invalidate();
				}
			} catch ( error ) {
				fail( error );
			}
		},
		look( place ) {
			if ( closed ) {
				return;
			}
			if ( trick ) {
				held = place;
				return;
			}
			go( place );
		},
		play( played ) {
			if ( closed || reduced() ) {
				return Promise.resolve( false );
			}
			const next = queue.then(
				() =>
					new Promise< boolean >( ( done ) => {
						if ( closed || reduced() ) {
							done( false );
							return;
						}
						trick = {
							start: view.performance.now(),
							trick: played,
							done,
						};
						// The shopper's drag waits for the board to land, as a camera button does.
						controls.enabled = false;
						invalidate();
					} )
			);
			queue = next;
			return next;
		},
		close,
		hold( pinned, progress ) {
			if ( closed || trick ) {
				return;
			}
			if ( pinned ) {
				pinned.pose( board, progress );
			} else {
				rest();
			}
			invalidate();
		},
		probe() {
			const groups: Partial< Record< Slot, string > > = {};
			for ( const [ slot, { group } ] of drawn ) {
				if ( group ) {
					groups[ slot ] = group.uuid;
				}
			}
			return {
				frames: renderer.info.render.frame,
				geometries: renderer.info.memory.geometries,
				textures: renderer.info.memory.textures,
				groups,
				closed,
				playing: undefined !== trick,
				board: [
					...board.position.toArray(),
					board.rotation.x,
					board.rotation.y,
					board.rotation.z,
				],
				camera: [
					...camera.position.toArray(),
					...controls.target.toArray(),
				],
			};
		},
	};
}
