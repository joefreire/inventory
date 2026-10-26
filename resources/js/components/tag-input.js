/** Tags field "a, b, c" + chips of existing tags (data-add-tag). */
export class TagInput {
    constructor(input) {
        this.input = input;
        document.querySelectorAll('[data-add-tag]').forEach((chip) => {
            chip.addEventListener('click', () => this.add(chip.dataset.addTag));
        });
    }

    names() { return this.input.value.split(',').map((t) => t.trim()).filter(Boolean); }

    add(name) {
        const names = this.names();
        if (!names.some((t) => t.toLowerCase() === name.toLowerCase())) names.push(name);
        this.input.value = names.join(', ');
    }

    set(names) { this.input.value = names.join(', '); }
}
