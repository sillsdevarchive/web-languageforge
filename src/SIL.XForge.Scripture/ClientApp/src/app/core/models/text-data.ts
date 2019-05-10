import Quill, { DeltaStatic } from 'quill';
import { RealtimeData } from 'xforge-common/models/realtime-data';
import { RealtimeDoc } from 'xforge-common/realtime-doc';
import { RealtimeOfflineStore } from 'xforge-common/realtime-offline-store';
import { Text } from './text';

export const Delta: new () => DeltaStatic = Quill.import('delta');

export type TextType = 'source' | 'target';

export function getTextDataIdStr(textId: string, chapter: number, textType: TextType): string {
  return `${textId}:${chapter}:${textType}`;
}

export class TextDataId {
  constructor(
    public readonly textId: string,
    public readonly chapter: number,
    public readonly textType: TextType = 'target'
  ) {}

  toString(): string {
    return getTextDataIdStr(this.textId, this.chapter, this.textType);
  }
}

/** Records in the text_data collection in the local or remote database are the content
 * of a chapter of a Scripture book. */
export class TextData extends RealtimeData<DeltaStatic, DeltaStatic> {
  static readonly TYPE = Text.TYPE;

  constructor(doc: RealtimeDoc, store: RealtimeOfflineStore) {
    super(TextData.TYPE, doc, store);
  }

  getSegmentCount(): { translated: number; blank: number } {
    let blank = 0;
    let translated = 0;
    for (const op of this.data.ops) {
      if (op.attributes && op.attributes.segment) {
        if (op.insert.blank) {
          if (op.insert.blank === 'normal') {
            blank++;
          }
        } else {
          translated++;
        }
      }
    }
    return { translated, blank };
  }

  protected prepareDataForStore(data: DeltaStatic): any {
    return { ops: data.ops };
  }
}
