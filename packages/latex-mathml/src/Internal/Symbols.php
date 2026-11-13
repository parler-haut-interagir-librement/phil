<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

/**
 * The commands that stand for one character or one name: identifiers, operators, large operators, functions,
 * delimiters, accents and spaces.
 *
 * @internal
 */
final class Symbols
{
    /** Letters and constants, written as identifiers; capital Greek letters upright. */
    public const array IDENTIFIERS = [
        'alpha' => 'α', 'beta' => 'β', 'gamma' => 'γ', 'delta' => 'δ', 'epsilon' => 'ϵ', 'varepsilon' => 'ε',
        'zeta' => 'ζ', 'eta' => 'η', 'theta' => 'θ', 'vartheta' => 'ϑ', 'iota' => 'ι', 'kappa' => 'κ',
        'lambda' => 'λ', 'mu' => 'μ', 'nu' => 'ν', 'xi' => 'ξ', 'pi' => 'π', 'varpi' => 'ϖ', 'rho' => 'ρ',
        'varrho' => 'ϱ', 'sigma' => 'σ', 'varsigma' => 'ς', 'tau' => 'τ', 'upsilon' => 'υ', 'phi' => 'ϕ',
        'varphi' => 'φ', 'chi' => 'χ', 'psi' => 'ψ', 'omega' => 'ω',
        'infty' => '∞', 'partial' => '∂', 'nabla' => '∇', 'emptyset' => '∅', 'varnothing' => '∅', 'hbar' => 'ℏ',
        'ell' => 'ℓ', 'Re' => 'ℜ', 'Im' => 'ℑ', 'aleph' => 'ℵ', 'wp' => '℘', 'imath' => 'ı', 'jmath' => 'ȷ',
    ];

    /** Capital Greek letters, upright identifiers. */
    public const array UPRIGHT = [
        'Gamma' => 'Γ', 'Delta' => 'Δ', 'Theta' => 'Θ', 'Lambda' => 'Λ', 'Xi' => 'Ξ', 'Pi' => 'Π', 'Sigma' => 'Σ',
        'Upsilon' => 'Υ', 'Phi' => 'Φ', 'Psi' => 'Ψ', 'Omega' => 'Ω',
    ];

    /** Binary operators, relations, arrows and punctuation, written as operators. */
    public const array OPERATORS = [
        'times' => '×', 'cdot' => '⋅', 'div' => '÷', 'pm' => '±', 'mp' => '∓', 'ast' => '∗', 'star' => '⋆',
        'circ' => '∘', 'bullet' => '∙', 'oplus' => '⊕', 'ominus' => '⊖', 'otimes' => '⊗', 'odot' => '⊙',
        'cup' => '∪', 'cap' => '∩', 'setminus' => '∖', 'wedge' => '∧', 'land' => '∧', 'vee' => '∨', 'lor' => '∨',
        'neg' => '¬', 'lnot' => '¬', 'forall' => '∀', 'exists' => '∃', 'nexists' => '∄',
        'leq' => '≤', 'le' => '≤', 'geq' => '≥', 'ge' => '≥', 'neq' => '≠', 'ne' => '≠', 'll' => '≪', 'gg' => '≫',
        'approx' => '≈', 'equiv' => '≡', 'sim' => '∼', 'simeq' => '≃', 'cong' => '≅', 'propto' => '∝',
        'in' => '∈', 'notin' => '∉', 'ni' => '∋', 'subset' => '⊂', 'supset' => '⊃', 'subseteq' => '⊆',
        'supseteq' => '⊇', 'perp' => '⊥', 'parallel' => '∥', 'mid' => '∣', 'vdash' => '⊢', 'models' => '⊨',
        'to' => '→', 'rightarrow' => '→', 'leftarrow' => '←', 'gets' => '←', 'leftrightarrow' => '↔',
        'Rightarrow' => '⇒', 'Leftarrow' => '⇐', 'Leftrightarrow' => '⇔', 'implies' => '⟹', 'iff' => '⟺',
        'mapsto' => '↦', 'uparrow' => '↑', 'downarrow' => '↓', 'longrightarrow' => '⟶', 'longleftarrow' => '⟵',
        'ldots' => '…', 'dots' => '…', 'cdots' => '⋯', 'vdots' => '⋮', 'ddots' => '⋱', 'angle' => '∠',
        'prime' => '′', 'colon' => ':', '{' => '{', '}' => '}', '|' => '‖', '%' => '%', '&' => '&', '#' => '#',
        '$' => '$', '_' => '_',
    ];

    /** Large operators, whose limits go under and over in a displayed formula. */
    public const array LARGE = [
        'sum' => '∑', 'prod' => '∏', 'coprod' => '∐', 'bigcup' => '⋃', 'bigcap' => '⋂', 'bigoplus' => '⨁',
        'bigotimes' => '⨂', 'bigvee' => '⋁', 'bigwedge' => '⋀',
    ];

    /** Integrals, whose limits stay beside them. */
    public const array INTEGRALS = ['int' => '∫', 'iint' => '∬', 'iiint' => '∭', 'oint' => '∮'];

    /** Functions written upright and applied to what follows. */
    public const array FUNCTIONS = [
        'sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'arcsin', 'arccos', 'arctan', 'sinh', 'cosh', 'tanh', 'coth',
        'log', 'ln', 'lg', 'exp', 'det', 'dim', 'gcd', 'deg', 'arg', 'ker', 'hom', 'Pr',
    ];

    /** Functions whose limits go under in a displayed formula. */
    public const array LIMITS = ['lim', 'liminf', 'limsup', 'max', 'min', 'sup', 'inf'];

    /** What `\left`, `\right` and the delimiters of a matrix accept, each to its character; `.` is none. */
    public const array DELIMITERS = [
        '(' => '(', ')' => ')', '[' => '[', ']' => ']', '|' => '|', '/' => '/', '.' => '',
        '\\{' => '{', '\\}' => '}', '\\|' => '‖', '\\langle' => '⟨', '\\rangle' => '⟩', '\\lfloor' => '⌊',
        '\\rfloor' => '⌋', '\\lceil' => '⌈', '\\rceil' => '⌉', '\\vert' => '|', '\\Vert' => '‖',
        '\\lvert' => '|', '\\rvert' => '|', '\\lVert' => '‖', '\\rVert' => '‖',
    ];

    /** Delimiters that are commands outside `\left` and `\right`. */
    public const array FENCES = [
        'langle' => '⟨', 'rangle' => '⟩', 'lfloor' => '⌊', 'rfloor' => '⌋', 'lceil' => '⌈', 'rceil' => '⌉',
        'vert' => '|', 'Vert' => '‖', 'lvert' => '|', 'rvert' => '|', 'lVert' => '‖', 'rVert' => '‖',
    ];

    /** Accents over their argument, each to its mark and whether it stretches with it. */
    public const array ACCENTS = [
        'hat' => ['^', false], 'widehat' => ['^', true], 'tilde' => ['˜', false], 'widetilde' => ['˜', true],
        'bar' => ['¯', false], 'overline' => ['‾', true], 'vec' => ['→', false], 'overrightarrow' => ['→', true],
        'dot' => ['˙', false], 'ddot' => ['¨', false], 'check' => ['ˇ', false], 'breve' => ['˘', false],
        'acute' => ['´', false], 'grave' => ['`', false], 'overbrace' => ['⏞', true],
    ];

    /** Marks under their argument. */
    public const array UNDER = ['underline' => '_', 'underbrace' => '⏟'];

    /** Spaces, in em. */
    public const array SPACES = [
        ',' => '0.1667', 'thinspace' => '0.1667', ':' => '0.2222', '>' => '0.2222', 'medspace' => '0.2222',
        ';' => '0.2778', 'thickspace' => '0.2778', '!' => '-0.1667', 'quad' => '1', 'qquad' => '2', ' ' => '0.25',
    ];

    /** The fonts of `\mathbf` and its kin, each to its alphabet. */
    public const array FONTS = [
        'mathbf' => 'bold', 'boldsymbol' => 'bold', 'mathbb' => 'double-struck', 'mathcal' => 'script',
        'mathscr' => 'script', 'mathfrak' => 'fraktur', 'mathsf' => 'sans-serif', 'mathtt' => 'monospace',
        'mathit' => 'italic', 'mathrm' => 'normal',
    ];

    /** The matrices, each to its delimiters. */
    public const array MATRICES = [
        'matrix' => ['', ''], 'smallmatrix' => ['', ''], 'pmatrix' => ['(', ')'], 'bmatrix' => ['[', ']'],
        'Bmatrix' => ['{', '}'], 'vmatrix' => ['|', '|'], 'Vmatrix' => ['‖', '‖'], 'cases' => ['{', ''],
        'aligned' => ['', ''], 'align' => ['', ''], 'align*' => ['', ''], 'gathered' => ['', ''],
        'array' => ['', ''],
    ];
}
