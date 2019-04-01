import { Injectable } from '@angular/core';
import { RecordIdentity } from '@orbit/data';
import { JsonApiService } from 'xforge-common/json-api.service';
import { JsonDataId } from 'xforge-common/models/json-data';
import { RealtimeService } from 'xforge-common/realtime.service';
import { ResourceService } from 'xforge-common/resource.service';
import { QuestionData } from './models/question-data';

@Injectable({
  providedIn: 'root'
})
export class QuestionService extends ResourceService {
  constructor(jsonApiService: JsonApiService, private readonly realtimeService: RealtimeService) {
    super(QuestionData.TYPE, jsonApiService);
  }

  connect(id: JsonDataId): Promise<QuestionData> {
    return this.realtimeService.connect(this.dataIdentity(id));
  }

  disconnect(questionData: QuestionData): Promise<void> {
    return this.realtimeService.disconnect(questionData);
  }

  localDelete(id: JsonDataId) {
    this.realtimeService.localDelete(this.dataIdentity(id));
  }

  private dataIdentity(id: JsonDataId): RecordIdentity {
    return this.identity(id.toString());
  }
}