// jsdom has no layout: driver.js scrolls the highlighted element into view, which jsdom lacks.
Element.prototype.scrollIntoView = function (): void {}
