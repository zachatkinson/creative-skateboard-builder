/**
 * The scene's contract's fixture (M14): an 8.25″ deck, 32″ long on a 14.25″ wheelbase, with trucks, 54 mm wheels and
 * bolts, and no riser or grip. Each call is a copy of its own, so a test may change it.
 */
import type { Scene } from '../../../assets/src/builder/three/scene';
import fixture from './scene.json';

export function scene(): Scene {
	return structuredClone( fixture ) as Scene;
}
